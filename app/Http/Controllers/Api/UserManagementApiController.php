<?php
// app/Http/Controllers/Api/UserManagementApiController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserManagementApiController extends Controller
{
    protected UserRepository $userRepository;
    protected UserInvitationService $invitationService;
    protected UserDisplayService $displayService;
    protected MultiChannelInvitationService $multiChannelService;

    protected const DEFAULT_PER_PAGE = 15;
    protected const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100];
    protected const MAX_PER_PAGE = 100;

    /**
     * ✅ Canonical map of user-type slugs → backend integer constants.
     */
    protected const TYPE_SLUG_TO_CONSTANT = [
        'super_admin'          => User::TYPE_SUPER_ADMIN,
        'admin'                => User::TYPE_ADMIN,
        'landlord'             => User::TYPE_LANDLORD,
        'tenant'               => User::TYPE_TENANT,
        'field_agent'          => User::TYPE_FIELD_AGENT,
        'developer'            => User::TYPE_DEVELOPER,
        'security_personnel'   => User::TYPE_SECURITY_PERSONNEL,
        'former_landlord'      => User::TYPE_FORMER_LANDLORD,
        'contractor'           => User::TYPE_CONTRACTOR,
        'sanitation_personnel' => User::TYPE_SANITATION_PERSONNEL,
    ];

    /**
     * ✅ Slugs that can actually be created via the API.
     */
    protected const CREATABLE_TYPE_SLUGS = [
        'admin',
        'landlord',
        'tenant',
        'field_agent',
        'security_personnel',
        'sanitation_personnel',
    ];

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

    // ==================== BOM / RESPONSE HELPERS ====================

    protected function cleanBomResponse($response)
    {
        $content = $response->getContent();
        if ($content === null || $content === '') {
            return $response;
        }

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
    }

    protected function cleanJsonResponse($data, $status = 200, array $headers = [], $options = 0)
    {
        return $this->cleanBomResponse(response()->json($data, $status, $headers, $options));
    }

    protected function apiResponse(
        bool $success,
        $data = null,
        ?string $message = null,
        int $status = 200,
        array $extra = []
    ) {
        $payload = array_merge([
            'success' => $success,
            'data'    => $data,
            'message' => $message,
        ], $extra);

        return $this->cleanJsonResponse($payload, $status);
    }

    protected function apiError(string $message, int $status = 400, array $extra = [])
    {
        return $this->apiResponse(false, null, $message, $status, $extra);
    }

    // ==================== METADATA / FORM HELPERS ====================

    public function create()
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized.', 403);
        }

        $userTypes = $this->getFilteredUserTypes($currentUser);
        unset($userTypes[User::TYPE_FORMER_LANDLORD]);
        unset($userTypes['archived']);

        $availableRoles = $currentUser->isSuperAdmin()
            ? Role::where('slug', '!=', 'developer')->orderBy('priority')->get()
            : collect();

        return $this->apiResponse(true, [
            'user_types'                 => $userTypes,
            'statuses'                   => User::getUserStatuses(),
            'communication_status'       => $this->getCommunicationServiceStatus(),
            'available_roles'            => $availableRoles,
            'sanitation_supervisors'     => $this->getAvailableSanitationSupervisors(),
            'default_supervisor_settings' => ['can_be_supervisor' => false],
        ], 'Create form metadata loaded.');
    }

    public function edit($id)
    {
        $currentUser = auth()->user();
        $user = User::with('roles')->findOrFail($id);

        try {
            $this->authorizeUserEdit($user, $currentUser);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->apiError($e->getMessage(), $e->getStatusCode());
        }

        $availableRoles = $currentUser->isSuperAdmin()
            ? Role::where('slug', '!=', 'developer')->orderBy('priority')->get()
            : collect();

        return $this->apiResponse(true, [
            'user'                   => $this->formatUserForApi($user, $currentUser, true),
            'user_types'             => $this->getFilteredUserTypes($currentUser, $user),
            'statuses'               => User::getUserStatuses(),
            'communication_status'   => $this->getCommunicationServiceStatus(),
            'available_roles'        => $availableRoles,
            'sanitation_supervisors' => $this->getAvailableSanitationSupervisors(),
            'current_supervisor_id'  => $user->sanitationPersonnel?->supervisor_id,
        ], 'Edit form metadata loaded.');
    }

    // ==================== USER LISTING & SEARCH ====================

    public function index(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized. Only administrators can view users.', 403);
        }

        $perPage = min($this->getPerPage($request->get('per_page')), self::MAX_PER_PAGE);

        $users = $this->userRepository->getPaginatedUsers($request->all(), $currentUser, $perPage);

        $stats = $this->userRepository->getDashboardStats($currentUser, $request->all());

        $users->getCollection()->transform(fn($user) => $this->displayService->prepareForDisplay($user));

        return $this->apiResponse(true, $users->items(), null, 200, [
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
            ],
            'stats'                => $stats,
            'user_types'           => $this->getFilteredUserTypes($currentUser),
            'statuses'             => User::getUserStatuses(),
            'available_roles'      => Role::where('slug', '!=', 'developer')->orderBy('priority')->get(),
            'communication_status' => $this->getCommunicationServiceStatus(),
            'per_page_options'     => self::PER_PAGE_OPTIONS,
            'filters'              => $request->only(['search', 'type', 'status', 'role', 'start_date', 'end_date']),
        ]);
    }

    public function getStats(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized.', 403);
        }

        return $this->apiResponse(
            true,
            $this->userRepository->getDashboardStats($currentUser, $request->all())
        );
    }

    public function search(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin() && !$currentUser->isLandlord()) {
            return $this->apiError('Unauthorized.', 403);
        }

        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
            'type'  => 'nullable|string|in:landlord,tenant,field_agent,security_personnel,admin',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return $this->apiError('Validation failed.', 422, ['errors' => $validator->errors()]);
        }

        $limit = (int) $request->get('limit', 10);
        $query = $request->query('query');

        $userQuery = User::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
              ->orWhere('email', 'like', "%{$query}%")
              ->orWhere('phone', 'like', "%{$query}%");
        });

        if ($request->filled('type')) {
            $typeConstant = $this->resolveTypeConstant($request->input('type'));
            if ($typeConstant === null) {
                $userQuery->whereRaw('1 = 0');
            } else {
                $userQuery->where('type', $typeConstant);
            }
        }

        if (!$currentUser->isSuperAdmin()) {
            $userQuery->where('type', '!=', User::TYPE_SUPER_ADMIN);
        }

        $users = $userQuery->limit($limit)->get(['id', 'name', 'email', 'phone', 'type', 'photo']);

        return $this->apiResponse(true, $users->map(fn($user) => [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'type'       => $user->type,
            'type_label' => $user->type_name,
            'avatar'     => $user->avatar_url,
            'initials'   => $user->initials,
        ]));
    }

    // ==================== USER CRUD ====================

    public function show($id)
    {
        $currentUser = auth()->user();
        $user = User::with(['roles', 'creator', 'properties', 'invitations' => fn($q) => $q->latest()->limit(5)])
            ->findOrFail($id);

        try {
            $this->authorizeUserView($user, $currentUser);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->apiError($e->getMessage(), $e->getStatusCode());
        }

        $this->loadUserRelationships($user);

        return $this->apiResponse(true, $this->formatUserForApi($user, $currentUser, true), null, 200, [
            'stats'                => $this->userRepository->getUserStatistics($user),
            'communication_status' => $this->getCommunicationServiceStatus(),
        ]);
    }

    public function store(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized. Only administrators can create users.', 403);
        }

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|unique:users,phone',
            'username' => 'nullable|string|unique:users,username',
            'type'     => 'required|string|in:' . implode(',', self::CREATABLE_TYPE_SLUGS),
            'status'   => 'sometimes|string|in:' . implode(',', array_keys(User::getUserStatuses())),
            'gender'   => 'nullable|string|in:male,female,other',
            'digital_address' => 'nullable|string|max:100',
            'region'   => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'send_invitation' => 'boolean',
            'invitation_channels' => 'required_if:send_invitation,true|array',
            'invitation_channels.*' => 'in:sms,email,whatsapp',
            'invitation_type' => 'required_if:send_invitation,true|string|in:welcome,registration,account_setup,password_setup',
            'roles' => 'nullable|array|exists:roles,id',
            'can_be_supervisor' => 'sometimes|boolean',
            'sanitation_can_be_supervisor' => 'sometimes|boolean',
            'sanitation_personnel_role'    => 'nullable|string',
            'sanitation_personnel_status'  => 'nullable|string',
            'sanitation_vehicle_number'    => 'nullable|string',
            'sanitation_vehicle_type'      => 'nullable|string',
            'sanitation_emergency_contact' => 'nullable|string',
            'sanitation_hire_date'         => 'nullable|date',
            'sanitation_supervisor_id'     => 'nullable|exists:sanitation_personnel,id',
            'security_can_be_supervisor'        => 'sometimes|boolean',
            'security_supervisor_level'         => 'nullable|integer',
            'security_supervisor_score'         => 'nullable|integer',
            'security_supervisor_certifications'=> 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->apiError('Validation failed.', 422, ['errors' => $validator->errors()]);
        }

        $typeSlug = strtolower(trim((string) $request->input('type')));
        $typeConstant = $this->resolveTypeConstant($typeSlug);

        if ($typeConstant === null) {
            return $this->apiError('Invalid user type.', 422, [
                'errors' => ['type' => ['Invalid user type.']],
            ]);
        }

        DB::beginTransaction();

        try {
            $userData = $request->all();
            $userData['type'] = $typeConstant;

            $isSanitation = $typeConstant === User::TYPE_SANITATION_PERSONNEL;
            $isSanitationEnabled = $request->boolean('sanitation_can_be_supervisor', false);

            if ($isSanitation && $isSanitationEnabled) {
                $userData['sanitation_can_be_supervisor']   = true;
                $userData['sanitation_personnel_role']      = $request->input('sanitation_personnel_role', 'worker');
                $userData['sanitation_personnel_status']    = $request->input('sanitation_personnel_status', 'active');
                $userData['sanitation_vehicle_number']      = $request->input('sanitation_vehicle_number');
                $userData['sanitation_vehicle_type']        = $request->input('sanitation_vehicle_type');
                $userData['sanitation_emergency_contact']   = $request->input('sanitation_emergency_contact');
                $userData['sanitation_hire_date']           = $request->input('sanitation_hire_date');
                $userData['sanitation_address']             = $request->input('address');
                $userData['sanitation_supervisor_id']       = $request->input('sanitation_supervisor_id');
            } else {
                $userData['sanitation_can_be_supervisor'] = false;
            }

            $isSecurity = $typeConstant === User::TYPE_SECURITY_PERSONNEL;
            $isSecurityEnabled = $request->boolean('security_can_be_supervisor', false);

            if ($isSecurity && $isSecurityEnabled) {
                $userData['security_can_be_supervisor']        = true;
                $userData['security_supervisor_level']         = $request->input('security_supervisor_level', 0);
                $userData['security_supervisor_score']         = $request->input('security_supervisor_score', 0);
                $userData['security_supervisor_certifications']= $request->input('security_supervisor_certifications');
            } else {
                $userData['security_can_be_supervisor'] = false;
            }

            if ($isSecurity) {
                $userData['can_be_supervisor'] = $request->boolean('can_be_supervisor', false) || $isSecurityEnabled;
            } else {
                $userData['can_be_supervisor'] = false;
            }

            $user = $this->userRepository->createUser($userData, $currentUser);

            $skipSpatieRoles = in_array($typeConstant, [
                User::TYPE_SANITATION_PERSONNEL,
                User::TYPE_SECURITY_PERSONNEL,
            ], true);

            if (!$skipSpatieRoles && $currentUser->isSuperAdmin() && $request->has('roles')) {
                $roleIds = array_filter($request->input('roles', []));
                if (!empty($roleIds)) {
                    $user->roles()->sync($roleIds);
                }
            }

            if ($user->type === User::TYPE_LANDLORD && !$user->hasRole('landlord')) {
                if ($landlordRole = Role::where('slug', 'landlord')->first()) {
                    $user->roles()->attach($landlordRole);
                }
            }

            $invitationResult = null;
            if ($request->boolean('send_invitation')) {
                $invitationResult = $this->invitationService->sendInvitation($user, [
                    'invitation_channels' => $request->input('invitation_channels', ['email']),
                    'invitation_type'     => $request->input('invitation_type', 'welcome'),
                    'expires_in_days'     => $request->input('expires_in_days', 7),
                ]);

                if ($invitationResult['success']) {
                    $user->update(['invitation_sent_at' => now()]);
                }
            }

            DB::commit();
            event(new UserCreated($user, $currentUser, $request->all()));

            Log::info('User created via API', [
                'user_id'    => $user->id,
                'created_by' => $currentUser->id,
                'type_slug'  => $typeSlug,
                'type_const' => $typeConstant,
            ]);

            return $this->apiResponse(
                true,
                $this->formatUserForApi($user, $currentUser),
                $this->buildSuccessMessage($user, $invitationResult),
                201,
                [
                    'invitation' => $invitationResult ? [
                        'sent'           => $invitationResult['success'],
                        'channels'       => $invitationResult['channels_successful'] ?? [],
                        'invitation_url' => $invitationResult['invitation_url'] ?? null,
                    ] : null,
                ]
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create user via API: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return $this->apiError('Failed to create user: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($id);

        try {
            $this->authorizeUserEdit($user, $currentUser);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->apiError($e->getMessage(), $e->getStatusCode());
        }

        $validator = Validator::make($request->all(), [
            'name'     => 'sometimes|string|max:255',
            'email'    => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'phone'    => ['nullable', 'string', Rule::unique('users')->ignore($user->id)],
            'username' => ['nullable', 'string', Rule::unique('users')->ignore($user->id)],
            'type'     => 'sometimes|string|in:' . implode(',', self::CREATABLE_TYPE_SLUGS),
            'status'   => 'sometimes|string|in:' . implode(',', array_keys(User::getUserStatuses())),
            'gender'   => 'nullable|string|in:male,female,other',
            'digital_address' => 'nullable|string|max:100',
            'region'   => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'roles'    => 'nullable|array|exists:roles,id',
            'remove_photo'               => 'sometimes|boolean',
            'remove_phone_verification'  => 'sometimes|boolean',
            'auto_verify_phone'          => 'sometimes|boolean',
            'can_be_supervisor'          => 'sometimes|boolean',
            'sanitation_can_be_supervisor' => 'sometimes|boolean',
            'sanitation_supervisor_id'     => 'nullable|exists:sanitation_personnel,id',
        ]);

        if ($validator->fails()) {
            return $this->apiError('Validation failed.', 422, ['errors' => $validator->errors()]);
        }

        DB::beginTransaction();

        try {
            $oldData  = $user->toArray();
            $oldRoles = $user->roles->pluck('id')->toArray();

            $updateData = $request->except([
                'remove_photo', 'remove_phone_verification', 'auto_verify_phone',
                'can_be_supervisor', 'roles',
            ]);

            if ($request->filled('type')) {
                $typeSlug = strtolower(trim((string) $request->input('type')));
                $typeConstant = $this->resolveTypeConstant($typeSlug);

                if ($typeConstant === null) {
                    DB::rollBack();
                    return $this->apiError('Invalid user type.', 422, [
                        'errors' => ['type' => ['Invalid user type.']],
                    ]);
                }

                $updateData['type'] = $typeConstant;
            }

            if ($request->boolean('remove_photo') && $user->photo) {
                if (Storage::disk('public')->exists('users/photos/' . $user->photo)) {
                    Storage::disk('public')->delete('users/photos/' . $user->photo);
                }
                $updateData['photo'] = null;
            }

            if ($request->boolean('remove_phone_verification')) {
                $updateData['phone_verified_at'] = null;
            } elseif ($request->boolean('auto_verify_phone') && $user->phone) {
                $updateData['phone_verified_at'] = now();
            }

            if ($request->has('can_be_supervisor')) {
                $updateData['can_be_supervisor'] = $request->boolean('can_be_supervisor');
            }

            $effectiveType = $updateData['type'] ?? $user->type;
            if ($effectiveType === User::TYPE_SANITATION_PERSONNEL) {
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

            $updated = $this->userRepository->updateUser($user, $updateData, $currentUser);
            if (!$updated) {
                throw new \Exception('Failed to update user');
            }

            $skipSpatieRoles = in_array($effectiveType, [
                User::TYPE_SANITATION_PERSONNEL,
                User::TYPE_SECURITY_PERSONNEL,
            ], true);

            if (!$skipSpatieRoles && $currentUser->isSuperAdmin() && $request->has('roles')) {
                $user->roles()->sync(array_filter($request->input('roles', [])));
            }

            if ($effectiveType === User::TYPE_LANDLORD && !$user->hasRole('landlord')) {
                if ($landlordRole = Role::where('slug', 'landlord')->first()) {
                    $user->roles()->attach($landlordRole);
                }
            }

            DB::commit();

            $changes = $this->getChanges($oldData, $user->fresh()->toArray());
            $changes['roles'] = ['old' => $oldRoles, 'new' => $user->roles->pluck('id')->toArray()];
            event(new UserUpdated($user, $currentUser, $changes));

            Log::info('User updated via API', ['user_id' => $user->id, 'updated_by' => $currentUser->id]);

            return $this->apiResponse(
                true,
                $this->formatUserForApi($user->fresh(), $currentUser, true),
                $this->buildUpdateSuccessMessage($user, $updateData)
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update user via API: ' . $e->getMessage(), ['user_id' => $user->id]);

            return $this->apiError('Failed to update user: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($id);

        try {
            $this->authorizeUserDeletion($user, $currentUser);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->apiError($e->getMessage(), $e->getStatusCode());
        }

        if ($user->id === $currentUser->id) {
            return $this->apiError('You cannot delete your own account.', 422);
        }

        DB::beginTransaction();
        try {
            $criticalRelations = $this->checkCriticalRelations($user);
            if (!empty($criticalRelations)) {
                DB::rollBack();
                return $this->apiError(
                    'Cannot delete user. Has: ' . implode(', ', $criticalRelations),
                    422,
                    ['critical_relations' => $criticalRelations]
                );
            }

            $deletionReason = $request->input('reason', 'Deleted via API by administrator');
            $result = $this->userRepository->softDeleteUser($user, $currentUser, $deletionReason);

            if (!$result) {
                throw new \Exception('Failed to delete user');
            }

            DB::commit();
            event(new UserDeleted($user, $currentUser, $deletionReason));

            Log::info('User soft deleted via API', ['user_id' => $user->id, 'deleted_by' => $currentUser->id]);

            return $this->apiResponse(true, null, 'User deleted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete user via API: ' . $e->getMessage(), ['user_id' => $user->id]);
            return $this->apiError('Failed to delete user: ' . $e->getMessage(), 500);
        }
    }

    public function forceDelete(Request $request, $id)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin()) {
            return $this->apiError('Only Super Administrators can permanently delete users.', 403);
        }

        $user = User::onlyTrashed()->findOrFail($id);

        if ($user->type === User::TYPE_DEVELOPER) {
            return $this->apiError('Cannot delete developer accounts.', 403);
        }

        DB::beginTransaction();
        try {
            $user->roles()->detach();

            if ($user->photo && Storage::disk('public')->exists('users/photos/' . $user->photo)) {
                Storage::disk('public')->delete('users/photos/' . $user->photo);
            }

            $user->forceDelete();

            DB::commit();
            Log::info('User permanently deleted via API', ['user_id' => $user->id, 'deleted_by' => $currentUser->id]);

            return $this->apiResponse(true, null, 'User permanently deleted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to permanently delete user: ' . $e->getMessage(), 500);
        }
    }

    public function forceDestroy(Request $request, $id)
    {
        return $this->forceDelete($request, $id);
    }

    public function restore($id)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized to restore users.', 403);
        }

        $user = User::onlyTrashed()->findOrFail($id);

        try {
            $this->authorizeUserRestore($user, $currentUser);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->apiError($e->getMessage(), $e->getStatusCode());
        }

        DB::beginTransaction();
        try {
            $user->restore();
            $user->update([
                'status'     => User::STATUS_PENDING,
                'deleted_at' => null,
                'deleted_by' => null,
            ]);

            DB::commit();
            Log::info('User restored via API', ['user_id' => $user->id, 'restored_by' => $currentUser->id]);

            return $this->apiResponse(
                true,
                $this->formatUserForApi($user->fresh(), $currentUser),
                'User restored successfully!'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to restore user: ' . $e->getMessage(), 500);
        }
    }

    // ==================== TRASHED USERS ====================

    public function getTrashedUsers(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized.', 403);
        }

        $perPage = min($this->getPerPage($request->get('per_page')), self::MAX_PER_PAGE);

        $query = User::onlyTrashed()->where('type', '!=', User::TYPE_DEVELOPER);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $typeConstant = $this->resolveTypeConstant($request->input('type'));
            if ($typeConstant === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('type', $typeConstant);
            }
        }

        if ($request->filled('deleted_by')) {
            $query->where('deleted_by', $request->deleted_by);
        }
        if (!$currentUser->isSuperAdmin()) {
            $query->where('type', '!=', User::TYPE_SUPER_ADMIN);
        }

        $users = $query->orderBy('deleted_at', 'desc')->paginate($perPage);

        $users->getCollection()->transform(function ($user) {
            // ✅ FIX: safe decode.
            $metadata = $this->_decodeMaybeJson($user->metadata) ?? [];
            $archivalInfo = $metadata['archival_info'] ?? [];

            return [
                'id'                     => $user->id,
                'name'                   => $user->name,
                'email'                  => $user->email,
                'phone'                  => $user->phone,
                'type'                   => $user->type,
                'type_label'             => $user->type_name,
                'deleted_at'             => $user->deleted_at?->toISOString(),
                'deletion_reason'        => $archivalInfo['archived_reason'] ?? 'No reason provided',
                'deleted_by'             => $archivalInfo['archived_by_name'] ?? 'System',
                'deletion_scheduled_at'  => $user->deletion_scheduled_at?->toISOString(),
                'can_restore'            => $this->canRestoreUser($user),
            ];
        });

        $trashedBase = User::onlyTrashed()->where('type', '!=', User::TYPE_DEVELOPER);

        return $this->apiResponse(true, $users->items(), null, 200, [
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
            ],
            'stats' => [
                'total_trashed'      => (clone $trashedBase)->count(),
                'trashed_this_week'  => (clone $trashedBase)->where('deleted_at', '>=', now()->subWeek())->count(),
                'trashed_this_month' => (clone $trashedBase)->where('deleted_at', '>=', now()->subMonth())->count(),
            ],
            'filters' => $request->only(['search', 'type', 'deleted_by']),
        ]);
    }

    public function getDeletedUserDetails($id)
    {
        $currentUser = auth()->user();

        try {
            $user = User::onlyTrashed()->findOrFail($id);

            if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
                return $this->apiError('Unauthorized.', 403);
            }

            $deletedBy = $user->deleted_by ? User::withTrashed()->find($user->deleted_by) : null;
            // ✅ FIX: safe decode.
            $metadata = $this->_decodeMaybeJson($user->metadata) ?? [];

            $deletionInfo = $metadata['deletion_info'] ?? [
                'reason'     => $metadata['archival_info']['archived_reason'] ?? 'No reason provided',
                'deleted_at' => $user->deleted_at,
                'deleted_by' => $user->deleted_by,
            ];

            return $this->apiResponse(true, [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'phone'          => $user->phone,
                'type'           => $user->type,
                'deleted_at'     => $user->deleted_at?->format('Y-m-d H:i:s'),
                'deleted_by_name'=> optional($deletedBy)->name ?? 'System',
                'deletion_reason'=> $deletionInfo['reason'] ?? 'Not specified',
                'can_restore'    => $this->canRestoreUser($user),
                'deletion_info'  => $deletionInfo,
            ]);

        } catch (\Exception $e) {
            return $this->apiError('Failed to load user details: ' . $e->getMessage(), 500);
        }
    }

    // ==================== ARCHIVED USERS ====================

    public function getArchivedUsers(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized.', 403);
        }

        $perPage = min($this->getPerPage($request->get('per_page')), self::MAX_PER_PAGE);
        $query = User::onlyTrashed()->where('type', '!=', User::TYPE_DEVELOPER);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $typeConstant = $this->resolveTypeConstant($request->input('type'));
            if ($typeConstant === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('type', $typeConstant);
            }
        }

        if ($request->filled('archival_period') && $request->archival_period !== 'all') {
            match ($request->archival_period) {
                'today' => $query->whereDate('deleted_at', today()),
                'week'  => $query->whereBetween('deleted_at', [now()->startOfWeek(), now()->endOfWeek()]),
                'month' => $query->whereMonth('deleted_at', now()->month),
                'year'  => $query->whereYear('deleted_at', now()->year),
                default => null,
            };
        }

        if ($request->filled('deletion_status') && $request->deletion_status !== 'all') {
            $request->deletion_status === 'scheduled'
                ? $query->whereNotNull('deletion_scheduled_at')
                : $query->whereNull('deletion_scheduled_at');
        }

        if (!$currentUser->isSuperAdmin()) {
            $query->where('type', '!=', User::TYPE_SUPER_ADMIN);
        }

        $users = $query->orderBy('deleted_at', 'desc')->paginate($perPage);

        $archivedUsers = $users->getCollection()->map(function ($user) {
            // ✅ FIX: safe decode.
            $metadata = $this->_decodeMaybeJson($user->metadata) ?? [];
            $archivalInfo = $metadata['archival_info'] ?? [];

            return [
                'id'                    => $user->id,
                'name'                  => $user->name,
                'email'                 => $user->email,
                'phone'                 => $user->phone,
                'type'                  => $user->type,
                'type_name'             => $user->type_name,
                'initials'              => $user->initials,
                'avatar_url'            => $user->avatar_url,
                'archived_at'           => $user->deleted_at?->toISOString(),
                'archival_reason'       => $archivalInfo['archived_reason'] ?? 'No reason provided',
                'archived_by_name'      => $archivalInfo['archived_by_name'] ?? 'System',
                'deletion_scheduled_at' => $user->deletion_scheduled_at?->toISOString(),
                'original_email'        => $archivalInfo['original_email'] ?? $user->email,
                'original_phone'        => $archivalInfo['original_phone'] ?? $user->phone,
                'days_since_archival'   => $user->deleted_at ? now()->diffInDays($user->deleted_at) : 0,
                'days_until_deletion'   => $user->deletion_scheduled_at
                    ? max(0, now()->diffInDays($user->deletion_scheduled_at, false))
                    : null,
            ];
        });

        $trashedBase = User::onlyTrashed()->where('type', '!=', User::TYPE_DEVELOPER);

        return $this->apiResponse(true, $archivedUsers, null, 200, [
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
            ],
            'stats' => [
                'total_archived'          => (clone $trashedBase)->count(),
                'archived_today'          => (clone $trashedBase)->whereDate('deleted_at', today())->count(),
                'archived_this_week'      => (clone $trashedBase)->where('deleted_at', '>=', now()->startOfWeek())->count(),
                'archived_this_month'     => (clone $trashedBase)->whereMonth('deleted_at', now()->month)->count(),
                'scheduled_for_deletion'  => (clone $trashedBase)->whereNotNull('deletion_scheduled_at')->count(),
                'pending_restore_requests'=> 0,
            ],
            'filters' => $request->only(['search', 'type', 'archival_period', 'deletion_status']),
        ]);
    }

    public function getArchivedUserDetails($id)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized.', 403);
        }

        $user = User::onlyTrashed()->findOrFail($id);

        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            return $this->apiError('Unauthorized.', 403);
        }

        // ✅ FIX: safe decode.
        $metadata = $this->_decodeMaybeJson($user->metadata) ?? [];

        return $this->apiResponse(true, [
            'id'                    => $user->id,
            'name'                  => $user->name,
            'email'                 => $user->email,
            'phone'                 => $user->phone,
            'username'              => $user->username,
            'type'                  => $user->type,
            'type_name'             => $user->type_name,
            'initials'              => $user->initials,
            'avatar_url'            => $user->avatar_url,
            'archived_at'           => $user->deleted_at?->toISOString(),
            'archival_reason'       => $metadata['archival_info']['archived_reason'] ?? 'No reason provided',
            'archived_by_name'      => $metadata['archival_info']['archived_by_name'] ?? 'System',
            'deletion_scheduled_at' => $user->deletion_scheduled_at?->toISOString(),
            'original_email'        => $metadata['archival_info']['original_email'] ?? $user->email,
            'original_phone'        => $metadata['archival_info']['original_phone'] ?? $user->phone,
            'days_since_archival'   => $user->deleted_at ? now()->diffInDays($user->deleted_at) : 0,
        ], null, 200, [
            'archival_info'    => $metadata['archival_info'] ?? [],
            'deletion_info'    => $metadata['deletion_info'] ?? [],
            'properties_count' => $user->properties()->count(),
        ]);
    }

    public function restoreArchivedUser($id)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized to restore archived users.', 403);
        }

        $user = User::onlyTrashed()->findOrFail($id);

        try {
            $this->authorizeUserRestore($user, $currentUser);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->apiError($e->getMessage(), $e->getStatusCode());
        }

        DB::beginTransaction();
        try {
            // ✅ FIX: safe decode.
            $metadata = $this->_decodeMaybeJson($user->metadata) ?? [];
            $archivalInfo = $metadata['archival_info'] ?? [];

            if (!empty($archivalInfo['original_email'])
                && !User::where('email', $archivalInfo['original_email'])->where('id', '!=', $user->id)->exists()) {
                $user->email = $archivalInfo['original_email'];
            }
            if (!empty($archivalInfo['original_phone'])
                && !User::where('phone', $archivalInfo['original_phone'])->where('id', '!=', $user->id)->exists()) {
                $user->phone = $archivalInfo['original_phone'];
            }
            if (!empty($archivalInfo['original_username'])
                && !User::where('username', $archivalInfo['original_username'])->where('id', '!=', $user->id)->exists()) {
                $user->username = $archivalInfo['original_username'];
            }

            $user->status = User::STATUS_PENDING;
            $user->deleted_by = null;
            $user->deletion_scheduled_at = null;

            unset($metadata['archival_info'], $metadata['deletion_info']);
            $user->metadata = !empty($metadata) ? $metadata : null;

            $user->save();
            $user->restore();

            DB::commit();
            Log::info('Archived user restored via API', ['user_id' => $user->id, 'restored_by' => $currentUser->id]);

            return $this->apiResponse(
                true,
                $this->formatUserForApi($user->fresh(), $currentUser),
                'User account restored successfully!'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to restore user: ' . $e->getMessage(), 500);
        }
    }

    public function bulkRestoreArchived(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized action.', 403);
        }

        $validator = Validator::make($request->all(), [
            'user_ids'   => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->apiError('Validation failed.', 422, ['errors' => $validator->errors()]);
        }

        $results = ['success' => 0, 'failed' => 0, 'restored_count' => 0, 'details' => []];

        DB::beginTransaction();
        try {
            foreach ($request->user_ids as $userId) {
                try {
                    $user = User::onlyTrashed()->find($userId);

                    if (!$user) {
                        $results['failed']++;
                        $results['details'][] = ['id' => $userId, 'success' => false, 'message' => 'User not found or not trashed'];
                        continue;
                    }

                    if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
                        $results['failed']++;
                        $results['details'][] = ['id' => $userId, 'success' => false, 'message' => 'Unauthorized on Super Admin'];
                        continue;
                    }

                    // ✅ FIX: safe decode.
                    $metadata = $this->_decodeMaybeJson($user->metadata) ?? [];
                    $archivalInfo = $metadata['archival_info'] ?? [];

                    if (!empty($archivalInfo['original_email'])
                        && !User::where('email', $archivalInfo['original_email'])->where('id', '!=', $user->id)->exists()) {
                        $user->email = $archivalInfo['original_email'];
                    }
                    if (!empty($archivalInfo['original_phone'])
                        && !User::where('phone', $archivalInfo['original_phone'])->where('id', '!=', $user->id)->exists()) {
                        $user->phone = $archivalInfo['original_phone'];
                    }
                    if (!empty($archivalInfo['original_username'])
                        && !User::where('username', $archivalInfo['original_username'])->where('id', '!=', $user->id)->exists()) {
                        $user->username = $archivalInfo['original_username'];
                    }

                    unset($metadata['archival_info'], $metadata['deletion_info']);
                    $user->metadata = !empty($metadata) ? $metadata : null;
                    $user->status = User::STATUS_PENDING;
                    $user->deleted_by = null;
                    $user->deletion_scheduled_at = null;
                    $user->save();
                    $user->restore();

                    $results['success']++;
                    $results['restored_count']++;
                    $results['details'][] = ['id' => $userId, 'success' => true, 'message' => 'User restored'];

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = ['id' => $userId, 'success' => false, 'message' => $e->getMessage()];
                }
            }
            DB::commit();

            return $this->apiResponse(
                $results['success'] > 0,
                null,
                "Bulk restore completed: {$results['success']} successful, {$results['failed']} failed",
                200,
                [
                    'restored_count' => $results['restored_count'],
                    'failed_count'   => $results['failed'],
                    'results'        => $results,
                ]
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiError('Bulk restore failed: ' . $e->getMessage(), 500);
        }
    }

    public function cancelArchivalSchedule($id)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized.', 403);
        }

        $user = User::onlyTrashed()->findOrFail($id);

        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            return $this->apiError('Unauthorized.', 403);
        }

        DB::beginTransaction();
        try {
            $user->deletion_scheduled_at = null;

            // ✅ FIX: safe decode.
            $metadata = $this->_decodeMaybeJson($user->metadata) ?? [];
            unset($metadata['deletion_info']);
            $user->metadata = !empty($metadata) ? $metadata : null;
            $user->save();

            DB::commit();

            return $this->apiResponse(true, null, 'Deletion schedule cancelled successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to cancel schedule: ' . $e->getMessage(), 500);
        }
    }

    public function emptyTrash(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin()) {
            return $this->apiError('Only Super Administrators can empty trash.', 403);
        }

        $validator = Validator::make($request->all(), [
            'confirmation' => 'required|string|in:empty_all_trash',
        ]);

        if ($validator->fails()) {
            return $this->apiError('Please confirm with "empty_all_trash" to proceed.', 422);
        }

        DB::beginTransaction();
        try {
            $trashedUsers = User::onlyTrashed()->where('type', '!=', User::TYPE_DEVELOPER)->get();

            $count = $trashedUsers->count();
            $deletedCount = 0;
            $failedUsers = [];

            foreach ($trashedUsers as $user) {
                try {
                    if ($user->id === $currentUser->id) {
                        $failedUsers[] = 'Cannot delete your own account';
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
                }
            }

            DB::commit();

            Log::info('Trash emptied via API', [
                'total_users'   => $count,
                'deleted_count' => $deletedCount,
                'failed_count'  => count($failedUsers),
                'deleted_by'    => $currentUser->id,
            ]);

            return $this->apiResponse(
                true,
                null,
                "Trash emptied successfully! {$deletedCount} out of {$count} users were permanently deleted.",
                200,
                [
                    'deleted_count' => $deletedCount,
                    'total_count'   => $count,
                    'failed_users'  => $failedUsers,
                ]
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to empty trash: ' . $e->getMessage(), 500);
        }
    }

    // ==================== USER STATUS ====================

    public function activate($id)
    {
        $currentUser = auth()->user();

        try {
            $user = $this->findUserAndAuthorizeAction($id, 'activate');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->apiError($e->getMessage(), $e->getStatusCode());
        }

        if ($user->update(['status' => User::STATUS_ACTIVE])) {
            Log::info('User activated via API', ['user_id' => $user->id, 'activated_by' => $currentUser->id]);

            return $this->apiResponse(
                true,
                $this->formatUserForApi($user->fresh(), $currentUser),
                'User account activated successfully!'
            );
        }

        return $this->apiError('Failed to activate user account.', 500);
    }

    public function suspend(Request $request, $id)
    {
        $currentUser = auth()->user();

        try {
            $user = $this->findUserAndAuthorizeAction($id, 'suspend');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->apiError($e->getMessage(), $e->getStatusCode());
        }

        if ($user->id === $currentUser->id) {
            return $this->apiError('You cannot suspend your own account.', 422);
        }

        $reason = $request->input('reason', 'No reason provided');

        if ($user->update([
            'status'            => User::STATUS_SUSPENDED,
            'suspension_reason' => $reason,
            'suspended_at'      => now(),
            'suspended_by'      => $currentUser->id,
        ])) {
            Log::info('User suspended via API', ['user_id' => $user->id, 'suspended_by' => $currentUser->id]);

            return $this->apiResponse(
                true,
                $this->formatUserForApi($user->fresh(), $currentUser),
                'User account suspended successfully!'
            );
        }

        return $this->apiError('Failed to suspend user account.', 500);
    }

    public function deactivate(Request $request, $id)
    {
        $currentUser = auth()->user();

        try {
            $user = $this->findUserAndAuthorizeAction($id, 'deactivate');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->apiError($e->getMessage(), $e->getStatusCode());
        }

        if ($user->id === $currentUser->id) {
            return $this->apiError('You cannot deactivate your own account.', 422);
        }

        $reason = $request->input('reason', 'Account deactivated by administrator');

        if ($user->update([
            'status'              => User::STATUS_INACTIVE,
            'deactivation_reason' => $reason,
            'deactivated_at'      => now(),
            'deactivated_by'      => $currentUser->id,
        ])) {
            Log::info('User deactivated via API', ['user_id' => $user->id, 'deactivated_by' => $currentUser->id]);

            return $this->apiResponse(
                true,
                $this->formatUserForApi($user->fresh(), $currentUser),
                'User account deactivated successfully!'
            );
        }

        return $this->apiError('Failed to deactivate user account.', 500);
    }

    // ==================== INVITATIONS ====================

    public function sendInvitation(Request $request, $id)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($id);

        try {
            $this->authorizeInvitationSend($user, $currentUser);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->apiError($e->getMessage(), $e->getStatusCode());
        }

        $validator = Validator::make($request->all(), [
            'invitation_channels'   => 'required|array',
            'invitation_channels.*' => 'in:sms,email,whatsapp',
            'invitation_type'       => 'required|in:welcome,registration,account_setup,password_setup',
            'custom_message'        => 'nullable|string|max:1000',
            'expires_in_days'       => 'nullable|integer|min:1|max:30',
        ]);

        if ($validator->fails()) {
            return $this->apiError('Validation failed.', 422, ['errors' => $validator->errors()]);
        }

        $result = $this->invitationService->sendInvitation($user, $request->all());

        if ($result['success']) {
            $user->update(['invitation_sent_at' => now()]);
            Log::info('Invitation sent via API', ['user_id' => $user->id, 'sent_by' => $currentUser->id]);

            return $this->apiResponse(true, [
                'channels_successful' => $result['channels_successful'],
                'failed_channels'     => $result['failed_channels'] ?? [],
                'invitation_url'      => $result['invitation_url'] ?? null,
            ], 'Invitation sent successfully!');
        }

        return $this->apiError($result['message'] ?? 'Failed to send invitation', 500);
    }

    public function resendInvitation(Request $request, $id)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($id);

        try {
            $this->authorizeInvitationSend($user, $currentUser);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->apiError($e->getMessage(), $e->getStatusCode());
        }

        $validator = Validator::make($request->all(), [
            'channels'        => 'sometimes|array',
            'channels.*'      => 'in:sms,email,whatsapp',
            'custom_message'  => 'nullable|string|max:1000',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
            'resend_type'     => 'nullable|in:same_channels,available_channels,selected_channels',
            'invitation_type' => 'nullable|in:welcome,registration,account_setup,password_setup',
        ]);

        if ($validator->fails()) {
            return $this->apiError('Validation failed.', 422, ['errors' => $validator->errors()]);
        }

        $result = $this->invitationService->resendInvitation($user, $request->all());

        if ($result['success']) {
            Log::info('Invitation resent via API', ['user_id' => $user->id, 'resent_by' => $currentUser->id]);

            return $this->apiResponse(true, [
                'channels_successful' => $result['channels_successful'],
                'failed_channels'     => $result['failed_channels'] ?? [],
                'invitation_url'      => $result['invitation_url'] ?? null,
            ], 'Invitation resent successfully!');
        }

        return $this->apiError($result['message'] ?? 'Failed to resend invitation', 500);
    }

    public function getInvitationInfo($id)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized.', 403);
        }

        $user = User::with(['invitations' => fn($q) => $q->orderBy('created_at', 'desc')->limit(1)])->findOrFail($id);
        $lastInvitation = $user->invitations->first();

        if ($lastInvitation) {
            // ✅ FIX: safe decode for channels (was `is_string(...) ? ... : ...`).
            $channels = $this->_decodeMaybeJson($lastInvitation->channels) ?? [];

            return $this->apiResponse(true, [
                'sent_at'    => $lastInvitation->created_at->toISOString(),
                'expires_at' => $lastInvitation->expires_at?->toISOString(),
                'channels'   => $channels,
                'type'       => $lastInvitation->type ?? 'welcome',
            ]);
        }

        return $this->apiError('No previous invitation found', 404);
    }

    public function getAvailableChannels($id)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized.', 403);
        }

        $user = User::findOrFail($id);

        return $this->apiResponse(true, [
            'available_channels' => $this->invitationService->getAvailableChannels($user),
            'user' => [
                'id'        => $user->id,
                'name'      => $user->name,
                'has_phone' => !empty($user->phone),
                'has_email' => !empty($user->email),
            ],
        ]);
    }

    // ==================== ROLES ====================

    public function getUserRoles($id)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin()) {
            return $this->apiError('Only Super Administrators can manage roles.', 403);
        }

        $user = User::with('roles')->findOrFail($id);

        if ($user->type === User::TYPE_DEVELOPER) {
            return $this->apiError('Cannot manage roles for developer accounts.', 403);
        }

        $availableRoles = Role::where('slug', '!=', 'developer')->orderBy('priority')->get();

        return $this->apiResponse(true, [
            'user' => [
                'id'               => $user->id,
                'name'             => $user->name,
                'email'            => $user->email,
                'type'             => $user->type,
                'is_property_owner'=> $user->is_property_owner,
                'property_count'   => $user->properties()->count(),
                'current_roles'    => $user->roles->map(function ($r) {
                    // ✅ Same safe-decode pattern as formatUserForApi.
                    $pivot = $r->pivot ?? null;
                    return [
                        'id'           => $r->id,
                        'name'         => $r->name,
                        'slug'         => $r->slug,
                        'display_name' => $r->display_name ?? $r->name,
                        'description'  => $r->description,
                        'metadata'             => $this->_decodeMaybeJson($pivot->metadata ?? null),
                        'permissions_override' => $this->_decodeMaybeJson($pivot->permissions_override ?? null),
                        'assigned_at'  => $pivot->created_at ?? null,
                        'expires_at'   => $pivot->expires_at ?? null,
                    ];
                }),
            ],
            'available_roles' => $availableRoles->map(fn($r) => [
                'id'           => $r->id,
                'name'         => $r->name,
                'slug'         => $r->slug,
                'display_name' => $r->display_name ?? $r->name,
                'description'  => $r->description,
                'priority'     => $r->priority,
            ]),
        ]);
    }

    public function updateUserRoles(Request $request, $id)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin()) {
            return $this->apiError('Only Super Administrators can assign roles.', 403);
        }

        $validator = Validator::make($request->all(), [
            'roles'   => 'array',
            'roles.*' => 'exists:roles,id',
        ]);

        if ($validator->fails()) {
            return $this->apiError('Validation failed.', 422, ['errors' => $validator->errors()]);
        }

        DB::beginTransaction();
        try {
            $user = User::findOrFail($id);
            if ($user->type === User::TYPE_DEVELOPER) {
                throw new \Exception('Cannot modify roles for developer accounts.');
            }

            $user->roles()->sync($request->input('roles', []));

            DB::commit();

            return $this->apiResponse(true, [
                'user_roles'   => $user->fresh()->roles->pluck('slug'),
                'role_details' => $user->fresh()->roles->map(fn($r) => ['id' => $r->id, 'name' => $r->name]),
            ], 'User roles updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to update roles: ' . $e->getMessage(), 500);
        }
    }

    public function getAvailableRoles()
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin()) {
            return $this->apiError('Unauthorized.', 403);
        }

        $roles = Role::where('slug', '!=', 'developer')->orderBy('priority')->get()->map(fn($role) => [
            'id'           => $role->id,
            'name'         => $role->name,
            'slug'         => $role->slug,
            'display_name' => $role->display_name ?? $role->name,
            'description'  => $role->description,
            'priority'     => $role->priority,
        ]);

        return $this->apiResponse(true, $roles);
    }

    public function assignLandlordRole(Request $request, $id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return $this->apiError('Unauthorized. Only Super Administrators can assign landlord role.', 403);
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
                Log::info('Landlord role assigned via API', ['user_id' => $user->id, 'assigned_by' => auth()->id()]);
            }

            DB::commit();
            return $this->apiResponse(true, null, 'Landlord role assigned successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to assign landlord role: ' . $e->getMessage(), 500);
        }
    }

    public function removeLandlordRole(Request $request, $id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return $this->apiError('Unauthorized. Only Super Administrators can remove landlord role.', 403);
        }

        DB::beginTransaction();
        try {
            $user = User::findOrFail($id);
            $propertyCount = $user->properties()->count();

            if ($propertyCount > 0) {
                $validator = Validator::make($request->all(), [
                    'force_remove' => 'sometimes|boolean',
                    'transfer_to'  => 'nullable|exists:users,id',
                ]);

                if ($validator->fails()) {
                    DB::rollBack();
                    return $this->apiError('Validation failed.', 422, ['errors' => $validator->errors()]);
                }

                if (!$request->boolean('force_remove')) {
                    DB::rollBack();
                    return $this->apiError(
                        "User owns {$propertyCount} property(s). You must either transfer properties or use force_remove flag.",
                        422,
                        [
                            'property_count'     => $propertyCount,
                            'requires_transfer'  => true,
                        ]
                    );
                }

                if ($request->filled('transfer_to')) {
                    $newOwner = User::find($request->transfer_to);
                    if ($newOwner) {
                        $transferred = Property::where('landlord_id', $user->id)->update(['landlord_id' => $newOwner->id]);
                        Log::info('Properties transferred during role removal', [
                            'from_user'      => $user->id,
                            'to_user'        => $newOwner->id,
                            'property_count' => $transferred,
                        ]);
                    }
                }
            }

            $landlordRole = Role::where('slug', 'landlord')->first();
            if ($landlordRole && $user->hasRole('landlord')) {
                $user->roles()->detach($landlordRole->id);
            }

            DB::commit();
            return $this->apiResponse(true, null, 'Landlord role removed successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to remove landlord role: ' . $e->getMessage(), 500);
        }
    }

    public function bulkRoleAction(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return $this->apiError('Unauthorized. Only Super Administrators can perform bulk role assignments.', 403);
        }

        $validator = Validator::make($request->all(), [
            'action'               => 'required|in:assign_landlord,remove_landlord',
            'user_ids'             => 'required|array',
            'user_ids.*'           => 'exists:users,id',
            'force_remove'         => 'sometimes|boolean',
            'transfer_to'          => 'nullable|exists:users,id',
            'skip_property_owners' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->apiError('Validation failed.', 422, ['errors' => $validator->errors()]);
        }

        $landlordRole = Role::where('slug', 'landlord')->first();
        if (!$landlordRole) {
            return $this->apiError('Landlord role not found.', 500);
        }

        $results = ['success' => 0, 'failed' => 0, 'skipped' => 0, 'details' => []];

        DB::beginTransaction();
        try {
            foreach ($request->user_ids as $userId) {
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

                    if ($request->action === 'assign_landlord') {
                        if (!$user->hasRole('landlord')) {
                            $user->roles()->attach($landlordRole);
                        }
                        $results['success']++;
                        $results['details'][] = ['id' => $userId, 'success' => true, 'message' => 'Landlord role assigned'];
                    } else {
                        $propertyCount = $user->properties()->count();

                        if ($propertyCount > 0 && !$request->boolean('force_remove')) {
                            if ($request->boolean('skip_property_owners')) {
                                $results['skipped']++;
                                $results['details'][] = [
                                    'id' => $userId, 'success' => true,
                                    'message' => "Skipped - User owns {$propertyCount} property(s)",
                                ];
                                continue;
                            }
                            $results['failed']++;
                            $results['details'][] = [
                                'id' => $userId, 'success' => false,
                                'message' => "User owns {$propertyCount} property(s). Use force_remove to override.",
                            ];
                            continue;
                        }

                        if ($user->hasRole('landlord')) {
                            $user->roles()->detach($landlordRole->id);
                        }
                        $results['success']++;
                        $results['details'][] = ['id' => $userId, 'success' => true, 'message' => 'Landlord role removed'];
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = ['id' => $userId, 'success' => false, 'message' => $e->getMessage()];
                }
            }
            DB::commit();

            $message = "Bulk role action completed: {$results['success']} successful, {$results['failed']} failed";
            if ($results['skipped'] > 0) {
                $message .= ", {$results['skipped']} skipped";
            }

            return $this->apiResponse($results['success'] > 0, null, $message, 200, ['results' => $results]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiError('Bulk role action failed: ' . $e->getMessage(), 500);
        }
    }

    // ==================== RELATION CHECK ====================

    public function checkRelations($id)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($id);

        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            return $this->apiError('Unauthorized.', 403);
        }

        $criticalRelations = $this->checkCriticalRelations($user);

        return $this->apiResponse(true, [
            'has_critical_relations' => !empty($criticalRelations),
            'critical_relations'     => $criticalRelations,
            'user_id'                => $user->id,
            'user_name'              => $user->name,
        ]);
    }

    // ==================== BULK OPERATIONS ====================

    public function bulkAction(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized action.', 403);
        }

        $validator = Validator::make($request->all(), [
            'action'              => 'required|in:activate,suspend,deactivate,delete,send_invitation',
            'user_ids'            => 'required|array',
            'user_ids.*'          => 'exists:users,id',
            'invitation_channels' => 'nullable|required_if:action,send_invitation|array',
            'invitation_type'     => 'nullable|required_if:action,send_invitation|in:welcome,registration,account_setup,password_setup',
            'reason'              => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->apiError('Validation failed.', 422, ['errors' => $validator->errors()]);
        }

        $results = ['success' => 0, 'failed' => 0, 'details' => []];

        foreach ($request->user_ids as $userId) {
            $result = $this->processBulkAction($userId, $request->action, $request->all(), $currentUser);
            $result['success'] ? $results['success']++ : $results['failed']++;
            $results['details'][] = $result;
        }

        return $this->apiResponse(
            $results['success'] > 0,
            null,
            "Bulk action completed: {$results['success']} successful, {$results['failed']} failed",
            200,
            ['results' => $results]
        );
    }

    public function bulkDelete(Request $request)
    {
        $request->merge(['action' => 'delete']);
        return $this->bulkAction($request);
    }

    public function bulkRestore(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized action.', 403);
        }

        $validator = Validator::make($request->all(), [
            'user_ids'   => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->apiError('Validation failed.', 422, ['errors' => $validator->errors()]);
        }

        $results = ['success' => 0, 'failed' => 0, 'restored_count' => 0, 'details' => []];

        DB::beginTransaction();
        try {
            foreach ($request->user_ids as $userId) {
                try {
                    $user = User::onlyTrashed()->find($userId);

                    if (!$user) {
                        $results['failed']++;
                        $results['details'][] = ['id' => $userId, 'success' => false, 'message' => 'User not found or not trashed'];
                        continue;
                    }

                    $user->restore();
                    $user->update([
                        'status'     => User::STATUS_PENDING,
                        'deleted_at' => null,
                        'deleted_by' => null,
                    ]);

                    $results['success']++;
                    $results['restored_count']++;
                    $results['details'][] = ['id' => $userId, 'success' => true, 'message' => 'User restored'];

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = ['id' => $userId, 'success' => false, 'message' => $e->getMessage()];
                }
            }
            DB::commit();

            return $this->apiResponse(
                $results['success'] > 0,
                null,
                "Bulk restore completed: {$results['success']} successful, {$results['failed']} failed",
                200,
                [
                    'restored_count' => $results['restored_count'],
                    'failed_count'   => $results['failed'],
                    'results'        => $results,
                ]
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiError('Bulk restore failed: ' . $e->getMessage(), 500);
        }
    }

    public function bulkPermanentDelete(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin()) {
            return $this->apiError('Only Super Administrators can permanently delete users.', 403);
        }

        $validator = Validator::make($request->all(), [
            'user_ids'   => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'reason'     => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->apiError('Validation failed.', 422, ['errors' => $validator->errors()]);
        }

        if (in_array($currentUser->id, $request->user_ids)) {
            return $this->apiError('You cannot permanently delete your own account.', 422);
        }

        $results = ['success' => 0, 'failed' => 0, 'deleted_count' => 0, 'details' => []];

        DB::beginTransaction();
        try {
            foreach ($request->user_ids as $userId) {
                try {
                    $user = User::onlyTrashed()->find($userId);

                    if (!$user) {
                        $results['failed']++;
                        $results['details'][] = ['id' => $userId, 'success' => false, 'message' => 'User not found or not trashed'];
                        continue;
                    }
                    if ($user->type === User::TYPE_DEVELOPER) {
                        $results['failed']++;
                        $results['details'][] = ['id' => $userId, 'success' => false, 'message' => 'Cannot delete developer accounts'];
                        continue;
                    }

                    $user->roles()->detach();
                    if ($user->photo && Storage::disk('public')->exists('users/photos/' . $user->photo)) {
                        Storage::disk('public')->delete('users/photos/' . $user->photo);
                    }
                    $user->forceDelete();

                    $results['success']++;
                    $results['deleted_count']++;
                    $results['details'][] = ['id' => $userId, 'success' => true, 'message' => 'User permanently deleted'];

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = ['id' => $userId, 'success' => false, 'message' => $e->getMessage()];
                }
            }
            DB::commit();

            return $this->apiResponse(
                $results['success'] > 0,
                null,
                "Bulk permanent delete completed: {$results['success']} successful, {$results['failed']} failed",
                200,
                [
                    'deleted_count' => $results['deleted_count'],
                    'failed_count'  => $results['failed'],
                    'results'       => $results,
                ]
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiError('Bulk permanent delete failed: ' . $e->getMessage(), 500);
        }
    }

    public function bulkPermanentDeleteArchived(Request $request)
    {
        return $this->bulkPermanentDelete($request);
    }

    // ==================== PROPERTY OWNERS ====================

    public function getPropertyOwners(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized.', 403);
        }

        $perPage = min($this->getPerPage($request->get('per_page')), self::MAX_PER_PAGE);

        $query = User::where(function ($q) {
            $q->whereHas('roles', fn($r) => $r->where('slug', 'landlord'))
              ->orWhere('type', User::TYPE_LANDLORD);
        })
        ->whereHas('properties')
        ->with([
            'properties' => fn($q) => $q->select(
                'id', 'landlord_id', 'property_name', 'digital_address', 'status',
                'property_type_id', 'custom_property_type', 'zone'
            ),
            'roles',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        if ($request->filled('multi_role') && $request->multi_role === 'yes') {
            $query->has('roles', '>', 1);
        }

        $propertyOwners = $query->orderBy('name')->paginate($perPage);

        $propertyOwners->getCollection()->transform(function ($user) {
            return [
                'id'                    => $user->id,
                'name'                  => $user->name,
                'email'                 => $user->email,
                'phone'                 => $user->phone,
                'type'                  => $user->type,
                'type_label'            => $user->type_name,
                'property_count'        => $user->properties->count(),
                'active_property_count' => $user->properties->where('status', 'active')->count(),
                'properties' => $user->properties->map(fn($p) => [
                    'id'              => $p->id,
                    'name'            => $p->property_name,
                    'digital_address' => $p->digital_address,
                    'status'          => $p->status,
                    'property_type'   => $p->custom_property_type ?? optional($p->propertyType)->name,
                    'city'            => $p->zone,
                ]),
                'roles'         => $user->roles->map(fn($r) => $r->display_name ?? $r->name),
                'is_multi_role' => $user->roles->count() > 1,
                'avatar'        => $user->avatar_url,
                'initials'      => $user->initials,
            ];
        });

        $baseQuery = User::where(function ($q) {
            $q->whereHas('roles', fn($r) => $r->where('slug', 'landlord'))
              ->orWhere('type', User::TYPE_LANDLORD);
        })->whereHas('properties');

        return $this->apiResponse(true, $propertyOwners->items(), null, 200, [
            'meta' => [
                'current_page' => $propertyOwners->currentPage(),
                'last_page'    => $propertyOwners->lastPage(),
                'per_page'     => $propertyOwners->perPage(),
                'total'        => $propertyOwners->total(),
            ],
            'stats' => [
                'total_property_owners'          => (clone $baseQuery)->count(),
                'total_properties'               => Property::count(),
                'multi_role_property_owners'     => User::whereHas('roles', fn($q) => $q->where('slug', 'landlord'))
                    ->whereHas('properties')->has('roles', '>', 1)->count(),
                'property_owners_without_phone'  => (clone $baseQuery)->whereNull('phone')->count(),
                'avg_properties_per_owner'       => round((clone $baseQuery)->withCount('properties')->get()->avg('properties_count') ?? 0, 1),
            ],
        ]);
    }

    // ==================== EXPORTS ====================

    public function export(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized to export users.', 403);
        }

        $format = $request->get('export_format', 'csv');

        $query = User::query();

        $userTypes = $request->input('user_types', ['all']);
        if (!in_array('all', $userTypes) && !empty($userTypes)) {
            $query->whereIn('type', $userTypes);
        }

        if ($request->boolean('current_filters')) {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
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
                $query->whereHas('roles', fn($q) => $q->where('slug', $request->role));
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

        $query->where('type', '!=', User::TYPE_DEVELOPER)->with(['roles', 'creator']);
        $users = $query->get();

        if ($format === 'csv') {
            $fileName = 'users-export-' . date('Y-m-d-His') . '.csv';
            $headers = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$fileName\"",
            ];

            $callback = function () use ($users) {
                $file = fopen('php://output', 'w');
                fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

                fputcsv($file, [
                    'ID', 'Name', 'Email', 'Phone', 'Username', 'Type', 'Roles',
                    'Status', 'Email Verified', 'Phone Verified', 'Has Photo',
                    'Property Owner', 'Property Count', 'Created At', 'Created By',
                    'Last Login', 'Registration IP',
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

        if ($format === 'pdf') {
            return $this->apiResponse(true, $users->map(fn($u) => [
                'id'               => $u->id,
                'name'             => $u->name,
                'email'            => $u->email,
                'phone'            => $u->phone,
                'username'         => $u->username,
                'type'             => $u->type,
                'type_label'       => $this->getUserTypeLabel($u->type),
                'roles'            => $u->roles->pluck('display_name'),
                'status'           => $u->status,
                'status_label'     => $this->getStatusLabel($u->status),
                'email_verified'   => !is_null($u->email_verified_at),
                'phone_verified'   => !is_null($u->phone_verified_at),
                'has_photo'        => !empty($u->photo),
                'photo_url'        => $u->photo_url ?? null,
                'is_property_owner'=> (bool) $u->is_property_owner,
                'property_count'   => $u->property_count ?? $u->properties()->count(),
                'created_at'       => $u->created_at->toISOString(),
                'created_by'       => $u->creator->name ?? 'System',
                'last_login_at'    => $u->last_login_at?->toISOString(),
            ]), null, 200, [
                'export_format' => 'pdf',
                'note'          => 'API returns structured JSON for PDF export. Client is responsible for rendering.',
                'meta' => [
                    'total'       => $users->count(),
                    'exported_at' => now()->toISOString(),
                    'exported_by' => $currentUser->name,
                    'statistics'  => $this->calculateExportStatistics($users),
                ],
            ]);
        }

        return $this->apiError('Unsupported export format.', 422);
    }

    public function exportPropertyOwners(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized to export property owners.', 403);
        }

        $format = $request->get('export_format', 'csv');

        $propertyOwners = User::where(function ($query) {
            $query->whereHas('roles', fn($q) => $q->where('slug', 'landlord'))
                  ->orWhere('type', User::TYPE_LANDLORD);
        })
        ->whereHas('properties')
        ->with(['properties', 'roles', 'creator'])
        ->get();

        if ($format === 'csv') {
            $fileName = 'property-owners-' . date('Y-m-d-His') . '.csv';
            $headers = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$fileName\"",
            ];

            $callback = function () use ($propertyOwners) {
                $file = fopen('php://output', 'w');
                fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

                fputcsv($file, [
                    'Name', 'Email', 'Phone', 'Property Count', 'Active Properties',
                    'Has Landlord Role', 'Multi-Role', 'Other Roles', 'Joined Date',
                    'Last Login', 'Status', 'Properties List',
                ]);

                foreach ($propertyOwners as $owner) {
                    $totalProperties = $owner->properties->count();
                    $propertyNames = $owner->properties->take(5)->pluck('property_name')->implode('; ');
                    if ($totalProperties > 5) {
                        $propertyNames .= " (+" . ($totalProperties - 5) . " more)";
                    }

                    fputcsv($file, [
                        $owner->name,
                        $owner->email,
                        $owner->phone,
                        $totalProperties,
                        $owner->properties->where('status', 'active')->count(),
                        $owner->hasRole('landlord') ? 'Yes' : 'No',
                        $owner->roles->count() > 1 ? 'Yes' : 'No',
                        $owner->roles->where('slug', '!=', 'landlord')->pluck('display_name')->implode(', ') ?: 'None',
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
            return $this->apiResponse(true, $propertyOwners->map(fn($owner) => [
                'id'                    => $owner->id,
                'name'                  => $owner->name,
                'email'                 => $owner->email,
                'phone'                 => $owner->phone,
                'property_count'        => $owner->properties->count(),
                'active_property_count' => $owner->properties->where('status', 'active')->count(),
                'roles'                 => $owner->roles->pluck('display_name'),
                'joined_at'             => $owner->created_at->toISOString(),
                'last_login_at'         => $owner->last_login_at?->toISOString(),
                'status'                => $owner->status,
            ]), null, 200, [
                'export_format' => 'pdf',
                'note'          => 'API returns structured JSON for PDF export. Client is responsible for rendering.',
                'meta' => [
                    'total'       => $propertyOwners->count(),
                    'exported_at' => now()->toISOString(),
                    'exported_by' => $currentUser->name,
                    'statistics'  => $this->calculateExportStatistics($propertyOwners),
                ],
            ]);
        }

        return $this->apiError('Unsupported export format.', 422);
    }

    // ==================== PASSWORD ====================

    public function updatePassword(Request $request, $id)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($id);

        try {
            $this->authorizePasswordChange($user, $currentUser);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->apiError($e->getMessage(), $e->getStatusCode());
        }

        $validator = Validator::make($request->all(), [
            'password'              => 'required|string|min:8|confirmed',
            'notify_user'           => 'boolean',
            'notification_channels' => 'sometimes|array|in:sms,email,whatsapp',
        ]);

        if ($validator->fails()) {
            return $this->apiError('Validation failed.', 422, ['errors' => $validator->errors()]);
        }

        DB::beginTransaction();
        try {
            $user->update(['password' => Hash::make($request->password)]);

            if ($request->boolean('notify_user')) {
                $this->sendPasswordChangeNotification($user, $request->input('notification_channels', ['email']));
            }

            DB::commit();
            Log::info('Password updated via API', ['user_id' => $user->id, 'updated_by' => $currentUser->id]);

            return $this->apiResponse(true, null, 'Password updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to update password: ' . $e->getMessage(), 500);
        }
    }

    public function markPhoneVerified($id)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized.', 403);
        }

        $user = User::findOrFail($id);

        if (!$user->phone) {
            return $this->apiError('User does not have a phone number to verify.', 422);
        }

        $user->update(['phone_verified_at' => now()]);

        Log::info('Phone marked as verified via API', ['user_id' => $user->id, 'verified_by' => $currentUser->id]);

        return $this->apiResponse(
            true,
            $this->formatUserForApi($user->fresh(), $currentUser),
            'Phone marked as verified successfully!'
        );
    }

    // ==================== ARCHIVE ====================

    public function archiveUser(Request $request, $id)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->apiError('Unauthorized. Only administrators can archive users.', 403);
        }

        try {
            $user = User::findOrFail($id);

            if ($user->type !== User::TYPE_LANDLORD) {
                return $this->apiError('Only landlord accounts can be archived.', 422);
            }
            if ($user->trashed()) {
                return $this->apiError('This user is already archived.', 422);
            }

            $propertyCount = $user->properties()->count();
            if ($propertyCount > 0) {
                return $this->apiError(
                    "Cannot archive landlord with {$propertyCount} active property(s). Transfer all properties first.",
                    422,
                    ['property_count' => $propertyCount]
                );
            }

            $settings = SystemSetting::getSettings();
            $permanentDeletionDays = $settings->account_archival['permanent_deletion_days'] ?? 365;
            $archivalReason = $request->input('reason', 'Landlord has no properties - manual archival by admin');

            $originalEmail = $user->email;
            $originalUsername = $user->username;
            $originalPhone = $user->phone;

            // ✅ FIX: safe decode.
            $metadata = $this->_decodeMaybeJson($user->metadata) ?? [];

            $metadata['archival_info'] = [
                'archived_at'                    => now()->toISOString(),
                'archived_reason'                => $archivalReason,
                'archived_by'                    => $currentUser->id,
                'archived_by_name'               => $currentUser->name,
                'original_email'                 => $originalEmail,
                'original_phone'                 => $originalPhone,
                'original_username'              => $originalUsername,
                'archival_type'                  => 'manual_admin',
                'property_count_at_archival'     => 0,
                'days_until_permanent_deletion'  => $permanentDeletionDays,
            ];

            $deletionScheduledAt = now()->addDays($permanentDeletionDays);
            $metadata['deletion_info'] = [
                'scheduled_at' => $deletionScheduledAt->toISOString(),
                'reason'       => 'Auto-deletion after archival period',
                'notified'     => false,
            ];

            $user->update([
                'status'                 => 'archived',
                'metadata'               => $metadata,
                'deletion_scheduled_at'  => $deletionScheduledAt,
                'deleted_by'             => $currentUser->id,
                'email'                  => $this->getArchivedEmail($originalEmail, $user->id),
                'username'               => $this->getArchivedUsername($originalUsername, $user->id),
            ]);

            $user->delete();

            Log::info('User archived via API', ['user_id' => $user->id, 'archived_by' => $currentUser->id]);

            return $this->apiResponse(
                true,
                null,
                "User account archived successfully! The account will be permanently deleted after {$permanentDeletionDays} days.",
                200,
                ['deletion_scheduled_at' => $deletionScheduledAt->toISOString()]
            );

        } catch (\Exception $e) {
            Log::error('Failed to archive user via API: ' . $e->getMessage(), ['user_id' => $id]);
            return $this->apiError('Failed to archive user: ' . $e->getMessage(), 500);
        }
    }

    // ==================== EMAIL COMPOSER ====================

    public function getUserListForEmail(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin() && !$currentUser->isDeveloper()) {
            return $this->apiError('Unauthorized. Only administrators and developers can access user lists.', 403);
        }

        try {
            $query = User::whereNotNull('email')
                ->where('email', '!=', '')
                ->where('type', '!=', User::TYPE_DEVELOPER);

            if ($currentUser->isAdmin() && !$currentUser->isSuperAdmin()) {
                $query->where('type', '!=', User::TYPE_SUPER_ADMIN);
            }

            if ($request->filled('type') && $request->type !== 'all') {
                $typeConstant = $this->resolveTypeConstant($request->input('type'));
                if ($typeConstant === null) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('type', $typeConstant);
                }
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

            return $this->apiResponse(true, $users->map(fn($user) => [
                'id'           => $user->id,
                'name'         => $user->name ?? 'Unknown User',
                'email'        => $user->email,
                'type'         => $user->type,
                'type_label'   => $this->getUserTypeLabel($user->type),
                'is_landlord'  => $user->type === User::TYPE_LANDLORD,
                'has_photo'    => !empty($user->photo),
                'photo_url'    => $user->photo_url ?? null,
                'status'       => $user->status,
                'status_label' => $this->getStatusLabel($user->status),
            ]));

        } catch (\Exception $e) {
            Log::error('Error loading users for email composer: ' . $e->getMessage());
            return $this->apiError('An error occurred while loading users: ' . $e->getMessage(), 500);
        }
    }

    // ==================== HELPERS ====================

    /**
     * ✅ Resolve a slug (or a numeric string, for backward compatibility)
     * into a `User::TYPE_*` integer constant.
     */
    private function resolveTypeConstant(?string $type): ?int
    {
        if ($type === null || $type === '') {
            return null;
        }

        $type = strtolower(trim($type));

        if (isset(self::TYPE_SLUG_TO_CONSTANT[$type])) {
            return self::TYPE_SLUG_TO_CONSTANT[$type];
        }

        if (ctype_digit($type)) {
            $asInt = (int) $type;
            if (in_array($asInt, array_values(self::TYPE_SLUG_TO_CONSTANT), true)) {
                return $asInt;
            }
        }

        return null;
    }

    /**
     * ✅ THE FIX for the "json_decode(): Argument #1 ($json) must be of type
     * string, array given" error.
     *
     * Laravel auto-decodes `JSON` columns and `array`-cast attributes, so a
     * value like `$role->pivot->metadata` can arrive as:
     *   • a JSON string    → decode it
     *   • an array         → return as-is
     *   • a stdClass       → cast to array
     *   • null / empty     → return null
     *   • malformed JSON   → return null (never throw)
     *
     * Calling `json_decode()` directly on an array throws a TypeError, which
     * was crashing `/api/v1/admin/users/{id}`. This helper makes the pattern
     * safe everywhere it's used.
     */
    private function _decodeMaybeJson($value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            // stdClass / JsonSerializable → plain array.
            return (array) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            try {
                $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                return is_array($decoded) ? $decoded : null;
            } catch (\JsonException $e) {
                return null;
            }
        }

        // Numbers, booleans, etc. — not valid JSON blobs, return null.
        return null;
    }

    private function getPerPage($requestedPerPage): int
    {
        $perPage = (int) $requestedPerPage;
        return in_array($perPage, self::PER_PAGE_OPTIONS) ? $perPage : self::DEFAULT_PER_PAGE;
    }

    private function getFilteredUserTypes(User $currentUser, ?User $targetUser = null): array
    {
        $userTypes = User::getUserTypes();

        unset($userTypes[User::TYPE_DEVELOPER]);
        unset($userTypes[User::TYPE_SUPER_ADMIN]);

        if (!$currentUser->isSuperAdmin()) {
            unset($userTypes[User::TYPE_ADMIN]);
        }

        unset($userTypes[User::TYPE_FORMER_LANDLORD]);

        return $userTypes;
    }

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

    private function authorizeInvitationSend(User $user, User $currentUser): void
    {
        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            abort(403, 'Unauthorized to send invitations to Super Admin users.');
        }
        if ($currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN && $user->id !== $currentUser->id) {
            abort(403, 'You can only send invitations for your own Super Admin account.');
        }
    }

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

    private function authorizeUserRestore(User $user, User $currentUser): void
    {
        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            abort(403, 'Unauthorized to restore Super Admin accounts.');
        }
        if ($currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            abort(403, 'You cannot restore other Super Admin accounts.');
        }
    }

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

    private function loadUserRelationships(User $user): void
    {
        try {
            $user->load(['creator']);
        } catch (\Exception $e) {
            $user->setRelation('creator', null);
        }
        try {
            $user->load(['properties' => fn($q) => $q->orderBy('created_at', 'desc')->limit(10)]);
        } catch (\Exception $e) {
            $user->setRelation('properties', collect());
        }
        try {
            $user->load(['invitations' => fn($q) => $q->orderBy('created_at', 'desc')->limit(5)]);
        } catch (\Exception $e) {
            $user->setRelation('invitations', collect());
        }
    }

    private function checkCriticalRelations(User $user): array
    {
        $critical = [];

        if ($user->type === User::TYPE_LANDLORD || $user->hasRole('landlord')) {
            $propertyCount = $user->properties()->count();
            if ($propertyCount > 0) {
                $critical[] = "{$propertyCount} properties";
            }
        }

        if ($user->type === User::TYPE_FIELD_AGENT && $user->assignedPlans()->where('status', 'active')->exists()) {
            $critical[] = 'active assignments';
        }

        if (in_array($user->type, [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])) {
            $createdUsers = User::where('created_by', $user->id)->count();
            if ($createdUsers > 0) {
                $critical[] = "{$createdUsers} created users";
            }
        }

        return $critical;
    }

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

            if ($user->id === $currentUser->id && in_array($action, ['suspend', 'deactivate', 'delete'])) {
                return ['id' => $userId, 'success' => false, 'message' => 'Cannot perform action on your own account'];
            }

            switch ($action) {
                case 'activate':
                    $user->update(['status' => User::STATUS_ACTIVE]);
                    return ['id' => $userId, 'success' => true, 'message' => 'Account activated'];

                case 'suspend':
                    $user->update([
                        'status'            => User::STATUS_SUSPENDED,
                        'suspension_reason' => $data['reason'] ?? 'Bulk action suspension',
                    ]);
                    return ['id' => $userId, 'success' => true, 'message' => 'Account suspended'];

                case 'deactivate':
                    $user->update([
                        'status'              => User::STATUS_INACTIVE,
                        'deactivation_reason' => $data['reason'] ?? 'Bulk action deactivation',
                    ]);
                    return ['id' => $userId, 'success' => true, 'message' => 'Account deactivated'];

                case 'delete':
                    $critical = $this->checkCriticalRelations($user);
                    if (!empty($critical)) {
                        return ['id' => $userId, 'success' => false, 'message' => 'Has critical relations: ' . implode(', ', $critical)];
                    }
                    $this->userRepository->softDeleteUser($user, $currentUser, $data['reason'] ?? 'Bulk action deletion');
                    return ['id' => $userId, 'success' => true, 'message' => 'Account deleted'];

                case 'send_invitation':
                    $result = $this->invitationService->sendInvitation($user, [
                        'invitation_channels' => $data['invitation_channels'] ?? ['email'],
                        'invitation_type'     => $data['invitation_type'] ?? 'welcome',
                        'custom_message'      => $data['custom_message'] ?? null,
                        'expires_in_days'     => $data['expires_in_days'] ?? 7,
                    ]);
                    return $result['success']
                        ? ['id' => $userId, 'success' => true, 'message' => 'Invitation sent']
                        : ['id' => $userId, 'success' => false, 'message' => $result['message'] ?? 'Failed'];

                default:
                    return ['id' => $userId, 'success' => false, 'message' => 'Unknown action'];
            }

        } catch (\Exception $e) {
            Log::error("Bulk action failed for user {$userId}: " . $e->getMessage());
            return ['id' => $userId, 'success' => false, 'message' => $e->getMessage()];
        }
    }

    private function buildSuccessMessage(User $user, ?array $invitationResult): string
    {
        $message = 'User created successfully!';
        if ($user->type === User::TYPE_LANDLORD) {
            $message .= ' Landlord role has been automatically assigned.';
        }
        if ($user->type === User::TYPE_SANITATION_PERSONNEL && $user->sanitationPersonnel) {
            $message .= " Sanitation personnel created with role: " . ucfirst($user->sanitationPersonnel->role ?? 'worker') . ".";
        }
        if ($invitationResult && $invitationResult['success']) {
            $channels = implode(', ', $invitationResult['channels_successful'] ?? []);
            $message .= " Invitation sent via {$channels}.";
            if (!empty($invitationResult['failed_channels'])) {
                $message .= " Failed channels: " . implode(', ', $invitationResult['failed_channels']) . ".";
            }
        }
        return $message;
    }

    private function buildUpdateSuccessMessage(User $user, array $data): string
    {
        $message = 'User updated successfully!';
        if (($data['type'] ?? null) === User::TYPE_LANDLORD && !$user->hasRole('landlord')) {
            $message .= ' Landlord role has been automatically assigned.';
        }
        return $message;
    }

    private function getChanges(array $oldData, array $newData): array
    {
        $changes = [];
        $excluded = ['updated_at', 'last_login_at', 'remember_token'];

        foreach ($newData as $key => $value) {
            if (in_array($key, $excluded)) {
                continue;
            }
            if (isset($oldData[$key]) && $oldData[$key] != $value) {
                $changes[$key] = ['old' => $oldData[$key], 'new' => $value];
            }
        }
        return $changes;
    }

    private function sendPasswordChangeNotification(User $user, array $channels): void
    {
        try {
            $message = "🔐 Password Update Notification\n\nHello {$user->name}!\n\nYour password has been updated by an administrator.\nIf you did not request this change, please contact support immediately.";
            $this->multiChannelService->sendMessage(
                $user,
                $message,
                'password_change_notification',
                ['changed_by' => auth()->id(), 'changed_at' => now()->toIso8601String()],
                $channels
            );
        } catch (\Exception $e) {
            Log::warning('Failed to send password change notification: ' . $e->getMessage(), ['user_id' => $user->id]);
        }
    }

    private function getCommunicationServiceStatus(): array
    {
        try {
            return cache()->remember('communication_service_status', 300, function () {
                return [
                    'sms'           => app(\App\Services\SmsService::class)->getSystemStatus() ?? [],
                    'email'         => app(\App\Services\EmailService::class)->getSystemStatus() ?? [],
                    'whatsapp'      => app(\App\Services\WhatsAppService::class)->getSystemStatus() ?? [],
                    'multi_channel' => $this->multiChannelService->getSystemStatus() ?? [],
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to get communication service status: ' . $e->getMessage());
            return [
                'sms'           => ['enabled' => false, 'status' => 'error', 'can_send' => false],
                'email'         => ['enabled' => false, 'status' => 'error', 'can_send' => false],
                'whatsapp'      => ['enabled' => false, 'status' => 'error', 'can_send' => false],
                'multi_channel' => ['enabled' => false, 'status' => 'error', 'can_send' => false],
            ];
        }
    }

    private function formatUserForApi(User $user, User $currentUser, bool $detailed = false): array
    {
        $prepared = $this->displayService->prepareForDisplay($user);

        if (is_array($prepared)) {
            $data = $prepared;
        } elseif ($prepared instanceof \Illuminate\Contracts\Support\Arrayable) {
            $data = $prepared->toArray();
        } elseif ($prepared instanceof User) {
            $data = $prepared->toArray();
        } elseif ($prepared instanceof \JsonSerializable) {
            $decoded = json_decode(json_encode($prepared), true);
            $data = is_array($decoded) ? $decoded : [];
        } else {
            $data = $user->toArray();
        }

        // ✅ Always include the creator in the API response.
        // Prefer the eager-loaded `creator` relation when present,
        // fall back to a lazy lookup if it wasn't eager-loaded.
        $creator = $user->relationLoaded('creator')
            ? $user->creator
            : $user->creator;

        if ($creator) {
            $data['creator'] = [
                'id'    => $creator->id,
                'name'  => $creator->name,
                'email' => $creator->email,
            ];
            $data['created_by_name'] = $creator->name;
        } else {
            $data['creator'] = null;
            $data['created_by_name'] = 'System';
        }

        $data['created_by'] = $user->created_by;

        if ($detailed) {
            $data['profile_completion'] = $this->calculateProfileCompletion($user);
            $data['property_count']     = $user->properties()->count();

            if ($user->isLandlord()) {
                $data['property_stats'] = [
                    'total'   => $user->properties()->count(),
                    'active'  => $user->properties()->where('status', 'active')->count(),
                    'pending' => $user->properties()->where('status', 'pending')->count(),
                ];
            }

            if ($user->isFieldAgent()) {
                $data['agent_stats'] = [
                    'total_assignments'     => $user->assignedPlans()->count(),
                    'active_assignments'    => $user->assignedPlans()->where('status', 'active')->count(),
                    'completed_assignments' => $user->assignedPlans()->where('status', 'completed')->count(),
                ];
            }

            if ($user->isTenant()) {
                $data['tenant_stats'] = [
                    'active_units'        => $user->propertyUnits()->where('tenant_status', 'approved')->count(),
                    'outstanding_balance' => $user->invoices()->where('status', '!=', 'paid')->sum('amount'),
                ];
            }

            $data['recent_invitations'] = $user->invitations()->latest()->limit(5)->get()->map(fn($inv) => [
                'id'         => $inv->id,
                'sent_at'    => $inv->created_at->toISOString(),
                'expires_at' => $inv->expires_at?->toISOString(),
                // ✅ FIX: safe decode for invitation channels.
                'channels'   => $this->_decodeMaybeJson($inv->channels) ?? [],
                'is_valid'   => $inv->isValid(),
                'used'       => !is_null($inv->used_at),
            ])->values()->all();
        }

        // ✅ FIX: roles now use `_decodeMaybeJson` for pivot metadata +
        // permissions_override — this was the exact line that crashed.
        $data['roles'] = $user->roles->map(function ($role) {
            $pivot = $role->pivot ?? null;

            return [
                'id'           => $role->id,
                'name'         => $role->name,
                'slug'         => $role->slug,
                'display_name' => $role->display_name ?? $role->name,
                'description'  => $role->description ?? null,
                'priority'     => $role->priority ?? null,

                'metadata'             => $this->_decodeMaybeJson($pivot->metadata ?? null),
                'permissions_override' => $this->_decodeMaybeJson($pivot->permissions_override ?? null),

                'assigned_at'  => $pivot->created_at ?? null,
                'expires_at'   => $pivot->expires_at ?? null,
                'is_active'    => isset($pivot->is_active) ? (bool) $pivot->is_active : true,
            ];
        })->values()->all();

        return $data;
    }

    private function calculateProfileCompletion(User $user): int
    {
        $fields = [
            'name'            => !empty($user->name),
            'email'           => !empty($user->email) && !is_null($user->email_verified_at),
            'phone'           => !empty($user->phone) && !is_null($user->phone_verified_at),
            'digital_address' => !empty($user->digital_address),
            'region'          => !empty($user->region),
            'location'        => !empty($user->location),
            'gender'          => !empty($user->gender),
            'dob'             => !empty($user->dob),
            'photo'           => !empty($user->photo),
            'username'        => !empty($user->username),
        ];

        $completed = count(array_filter($fields));
        $total = count($fields);

        return $total > 0 ? (int) round(($completed / $total) * 100) : 0;
    }

    private function calculateExportStatistics($users): array
    {
        $total = $users->count();

        $typeBreakdown = [];
        foreach ($users->groupBy('type') as $type => $group) {
            $typeBreakdown[$type] = [
                'count'      => $group->count(),
                'percentage' => $total > 0 ? round(($group->count() / $total) * 100, 1) : 0,
            ];
        }

        $statusBreakdown = [];
        foreach ($users->groupBy('status') as $status => $group) {
            $statusBreakdown[$status] = [
                'count'      => $group->count(),
                'percentage' => $total > 0 ? round(($group->count() / $total) * 100, 1) : 0,
            ];
        }

        $verifiedPhones = $users->filter(fn($u) => !is_null($u->phone_verified_at))->count();
        $verifiedEmails = $users->filter(fn($u) => !is_null($u->email_verified_at))->count();
        $withPhotos     = $users->filter(fn($u) => !empty($u->photo))->count();
        $propertyOwners = $users->filter(fn($u) => $u->is_property_owner)->count();

        return [
            'type_breakdown'      => $typeBreakdown,
            'status_breakdown'    => $statusBreakdown,
            'verified_phones'     => $verifiedPhones,
            'verified_emails'     => $verifiedEmails,
            'with_photos'         => $withPhotos,
            'property_owners'     => $propertyOwners,
            'multi_role_users'    => $users->filter(fn($u) => $u->roles->count() > 1)->count(),
            'landlord_role_count' => $users->filter(fn($u) => $u->hasRole('landlord'))->count(),
            'completion_rate'     => [
                'phone_verification' => $total > 0 ? round(($verifiedPhones / $total) * 100, 1) : 0,
                'email_verification' => $total > 0 ? round(($verifiedEmails / $total) * 100, 1) : 0,
                'profile_photos'     => $total > 0 ? round(($withPhotos / $total) * 100, 1) : 0,
            ],
        ];
    }

    private function canRestoreUser(User $user): bool
    {
        return !User::where(function ($q) use ($user) {
            $q->where('email', $user->email)->orWhere('phone', $user->phone);
        })->where('id', '!=', $user->id)->exists();
    }

    private function getArchivedEmail($originalEmail, $userId)
    {
        if (empty($originalEmail)) {
            return null;
        }
        if (strpos($originalEmail, '.archived.') !== false) {
            return $originalEmail;
        }

        [$username, $domain] = array_pad(explode('@', $originalEmail, 2), 2, 'example.com');
        return "{$username}.archived.{$userId}@{$domain}";
    }

    private function getArchivedUsername($originalUsername, $userId)
    {
        if (empty($originalUsername)) {
            return null;
        }
        if (strpos($originalUsername, '.archived.') !== false) {
            return $originalUsername;
        }
        return "{$originalUsername}.archived.{$userId}";
    }

    private function getUserTypeLabel(string $type): string
    {
        return [
            User::TYPE_SUPER_ADMIN         => 'Super Admin',
            User::TYPE_ADMIN               => 'Admin',
            User::TYPE_LANDLORD            => 'Landlord',
            User::TYPE_TENANT              => 'Tenant',
            User::TYPE_FIELD_AGENT         => 'Field Agent',
            User::TYPE_SECURITY_PERSONNEL  => 'Security Personnel',
            User::TYPE_SANITATION_PERSONNEL=> 'Sanitation Personnel',
            User::TYPE_CONTRACTOR          => 'Contractor',
            User::TYPE_DEVELOPER           => 'Developer',
        ][$type] ?? $type;
    }

    private function getStatusLabel(string $status): string
    {
        return [
            User::STATUS_ACTIVE    => 'Active',
            User::STATUS_PENDING   => 'Pending',
            User::STATUS_SUSPENDED => 'Suspended',
            User::STATUS_INACTIVE  => 'Inactive',
            'archived'             => 'Archived',
        ][$status] ?? $status;
    }

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
            Log::warning('Failed to load sanitation supervisors list', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
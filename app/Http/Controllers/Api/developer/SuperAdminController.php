<?php

namespace App\Http\Controllers\API\Developer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use App\Models\DeveloperSetting;
use App\Services\DeveloperEmailService;
use App\Services\DeveloperEmailConfigurationService;
use App\Services\PhotoService;
use App\Services\PasswordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class SuperAdminController extends Controller
{
    protected $developerEmailService;
    protected $emailConfigService;
    protected $photoService;
    protected $passwordService;

    public function __construct(
        DeveloperEmailService $developerEmailService,
        DeveloperEmailConfigurationService $emailConfigService,
        PhotoService $photoService,
        PasswordService $passwordService
    ) {
        $this->developerEmailService = $developerEmailService;
        $this->emailConfigService = $emailConfigService;
        $this->photoService = $photoService;
        $this->passwordService = $passwordService;
    }

    /**
     * Get list of Super Admins (with pagination and filters)
     * GET /api/developer/super-admins
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:pending,active,suspended,inactive,all',
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'sort_by' => 'nullable|string|in:name,email,created_at,last_login_at,status',
            'sort_direction' => 'nullable|string|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = User::where('type', User::TYPE_SUPER_ADMIN)
            ->where('created_by', auth()->id())
            ->with(['creator'])
            ->orderBy('created_at', 'desc');

        // Apply status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Apply date range filter
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        $perPage = $request->get('per_page', 20);
        $superAdmins = $query->paginate($perPage);

        // Transform data for API response
        $superAdmins->getCollection()->transform(function ($user) {
            return $this->formatUserForApi($user);
        });

        // Get statistics
        $statistics = $this->getStatistics();

        return response()->json([
            'success' => true,
            'data' => [
                'super_admins' => $superAdmins,
                'statistics' => $statistics,
                'filters' => [
                    'status' => $request->status,
                    'search' => $request->search,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                ]
            ]
        ]);
    }

    /**
     * Get trashed (soft-deleted) Super Admins
     * GET /api/developer/super-admins/trash
     */
    public function trash(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = User::onlyTrashed()
            ->where('type', User::TYPE_SUPER_ADMIN)
            ->where('created_by', auth()->id())
            ->with(['creator'])
            ->orderBy('deleted_at', 'desc');

        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Apply date range filter
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->whereDate('deleted_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->whereDate('deleted_at', '<=', $request->date_to);
        }

        $perPage = $request->get('per_page', 20);
        $trashedSuperAdmins = $query->paginate($perPage);

        // Transform data for API response
        $trashedSuperAdmins->getCollection()->transform(function ($user) {
            $formatted = $this->formatUserForApi($user);
            $formatted['deleted_at'] = $user->deleted_at ? $user->deleted_at->toISOString() : null;
            $formatted['deleted_at_formatted'] = $user->deleted_at ? $user->deleted_at->format('Y-m-d H:i:s') : null;
            $formatted['days_in_trash'] = $user->deleted_at ? $user->deleted_at->diffInDays(now()) : 0;
            $formatted['can_be_permanently_deleted'] = $this->canBePermanentlyDeleted($user);
            return $formatted;
        });

        // Trash statistics
        $statistics = [
            'total_trashed' => User::onlyTrashed()
                ->where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', auth()->id())
                ->count(),
            'trashed_this_week' => User::onlyTrashed()
                ->where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', auth()->id())
                ->whereBetween('deleted_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'trashed_this_month' => User::onlyTrashed()
                ->where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', auth()->id())
                ->whereMonth('deleted_at', now()->month)
                ->count(),
            'oldest_trashed_days' => $this->getOldestTrashedDays(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'trashed_super_admins' => $trashedSuperAdmins,
                'statistics' => $statistics
            ]
        ]);
    }

    /**
     * Get single Super Admin details
     * GET /api/developer/super-admins/{id}
     */
    public function show($id)
    {
        $user = User::with([
            'creator',
            'invitations' => function($query) {
                $query->orderBy('created_at', 'desc');
            }
        ])->withTrashed()->findOrFail($id);

        // Authorization check
        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only view Super Admins you created.'
            ], 403);
        }

        if ($user->type !== User::TYPE_SUPER_ADMIN) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a Super Admin.'
            ], 404);
        }

        $userData = $this->formatUserForApi($user);
        
        // Add invitation history
        $userData['invitation_history'] = $user->invitations->map(function($invitation) {
            return $this->formatInvitationForApi($invitation);
        });

        // Add invitation statistics
        $userData['invitation_stats'] = $this->getInvitationStats($user);

        // Add recent activities (if activity tracking exists)
        $userData['recent_activities'] = $this->getRecentActivities($user);

        // Add email configuration status
        $userData['email_configuration'] = $this->getEmailConfigurationStatus();

        // Check if user can receive invitation
        $userData['can_receive_invitation'] = $this->canReceiveInvitation($user);

        // Check if user has active invitation
        $userData['has_active_invitation'] = $this->hasActiveInvitation($user);

        return response()->json([
            'success' => true,
            'data' => $userData
        ]);
    }

    /**
     * Create a new Super Admin
     * POST /api/developer/super-admins
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20|unique:users,phone',
            'status' => 'required|string|in:pending,active',
            'gender' => 'nullable|string|in:male,female,other',
            'digital_address' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:100|unique:users,username',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'photo_base64' => 'nullable|string',
            'send_invitation' => 'required|boolean',
            'custom_message' => 'nullable|string|max:1000',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
            'auto_verify_phone' => 'sometimes|boolean',
            'auto_verify_email' => 'sometimes|boolean',
            'password' => 'required_if:send_invitation,false|nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check email configuration if sending invitation
        if ($request->boolean('send_invitation')) {
            $emailStatus = $this->getEmailConfigurationStatus();
            if (!$emailStatus['can_send']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email configuration is not set up or not working. Please configure email settings first.',
                    'error' => 'email_not_configured'
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            // Prepare user data
            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'type' => (int) User::TYPE_SUPER_ADMIN,
                'status' => $request->status,
                'gender' => $request->gender ?? null,
                'digital_address' => $request->digital_address ?? null,
                'region' => $request->region ?? null,
                'location' => $request->location ?? null,
                'username' => $request->username ?? null,
                'created_by' => auth()->id(),
                'metadata' => [
                    'created_via' => 'mobile_app',
                    'created_by_developer' => auth()->id(),
                    'developer_name' => auth()->user()->name,
                    'initial_status' => $request->status,
                    'setup_method' => $request->boolean('send_invitation') ? 'invitation' : 'manual_password',
                    'auto_verification_enabled' => [
                        'phone' => $request->boolean('auto_verify_phone'),
                        'email' => $request->boolean('auto_verify_email')
                    ],
                    'created_from' => 'flutter_app',
                    'created_at_timestamp' => now()->toISOString()
                ]
            ];

            // Handle password logic
            $temporaryPassword = null;
            $invitationPasswordHash = null;

            if ($request->boolean('send_invitation')) {
                $temporaryPassword = Str::random(32);
                $userData['password'] = Hash::make($temporaryPassword);
                $userData['status'] = User::STATUS_PENDING;
                $invitationPasswordHash = Hash::make($temporaryPassword);
            } else {
                $userData['password'] = Hash::make($request->password);
                $userData['status'] = User::STATUS_ACTIVE;
                
                if ($request->boolean('auto_verify_email')) {
                    $userData['email_verified_at'] = now();
                }
            }

            // Handle phone verification
            if ($request->boolean('auto_verify_phone')) {
                $userData['phone_verified_at'] = now();
            }

            // Handle photo upload
            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                $photoPath = $this->photoService->upload($request->file('photo'), 'super_admins');
                $userData['photo'] = $photoPath;
            } elseif ($request->has('photo_base64') && !empty($request->photo_base64)) {
                $photoPath = $this->uploadBase64Photo($request->photo_base64);
                if ($photoPath) {
                    $userData['photo'] = $photoPath;
                }
            }

            // Create super admin
            $user = User::create($userData);

            // Send invitation if requested
            $invitationResult = null;
            
            if ($request->boolean('send_invitation')) {
                $invitationResult = $this->sendSuperAdminInvitation($user, $request, $invitationPasswordHash);
                
                if ($invitationResult['success']) {
                    $user->update(['last_invitation_sent_at' => now()]);
                }
            }

            DB::commit();

            Log::info('Mobile Developer created Super Admin', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id,
                'super_admin_email' => $user->email,
                'setup_method' => $userData['metadata']['setup_method'],
                'invitation_sent' => $request->boolean('send_invitation')
            ]);

            $responseData = $this->formatUserForApi($user);
            
            if ($invitationResult) {
                $responseData['invitation'] = [
                    'sent' => $invitationResult['success'],
                    'message' => $invitationResult['message'],
                    'invitation_url' => $invitationResult['invitation_url'] ?? null,
                    'expires_in_days' => $request->expires_in_days ?? 7
                ];
            }

            // If manual password setup, include temporary password in response (only for this response)
            if (!$request->boolean('send_invitation') && $request->password) {
                $responseData['setup_password'] = $request->password;
            }

            return response()->json([
                'success' => true,
                'message' => $invitationResult && !$invitationResult['success'] 
                    ? 'Super Admin created but invitation failed: ' . $invitationResult['message']
                    : 'Super Admin created successfully!',
                'data' => $responseData
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create Super Admin via API', [
                'developer_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create Super Admin: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Super Admin
     * PUT /api/developer/super-admins/{id}
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Authorization check
        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only update Super Admins you created.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $id,
            'status' => 'sometimes|string|in:pending,active,suspended,inactive',
            'gender' => 'nullable|string|in:male,female,other',
            'digital_address' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:100|unique:users,username,' . $id,
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'photo_base64' => 'nullable|string',
            'remove_photo' => 'sometimes|boolean',
            'auto_verify_phone' => 'sometimes|boolean',
            'remove_phone_verification' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updateData = [];

            // Only update fields that are provided
            if ($request->has('name')) $updateData['name'] = $request->name;
            if ($request->has('email')) $updateData['email'] = $request->email;
            if ($request->has('phone')) $updateData['phone'] = $request->phone;
            if ($request->has('status')) $updateData['status'] = $request->status;
            if ($request->has('gender')) $updateData['gender'] = $request->gender;
            if ($request->has('digital_address')) $updateData['digital_address'] = $request->digital_address;
            if ($request->has('region')) $updateData['region'] = $request->region;
            if ($request->has('location')) $updateData['location'] = $request->location;
            if ($request->has('username')) $updateData['username'] = $request->username;

            // Handle photo
            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                if ($user->photo) {
                    $this->photoService->delete($user->photo, 'super_admins');
                }
                $photoPath = $this->photoService->upload($request->file('photo'), 'super_admins');
                $updateData['photo'] = $photoPath;
            } elseif ($request->has('photo_base64') && !empty($request->photo_base64)) {
                if ($user->photo) {
                    $this->photoService->delete($user->photo, 'super_admins');
                }
                $photoPath = $this->uploadBase64Photo($request->photo_base64);
                if ($photoPath) {
                    $updateData['photo'] = $photoPath;
                }
            }

            if ($request->boolean('remove_photo') && $user->photo) {
                $this->photoService->delete($user->photo, 'super_admins');
                $updateData['photo'] = null;
            }

            // Handle phone verification
            if ($request->boolean('auto_verify_phone')) {
                $updateData['phone_verified_at'] = now();
            } elseif ($request->boolean('remove_phone_verification')) {
                $updateData['phone_verified_at'] = null;
            }

            // Update metadata
            $metadata = $user->metadata ?? [];
            $metadata['last_updated_by_developer'] = [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'at' => now()->toISOString(),
                'source' => 'mobile_app'
            ];
            $updateData['metadata'] = $metadata;

            $user->update($updateData);

            DB::commit();

            Log::info('Mobile Developer updated Super Admin', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id,
                'updates' => array_keys($updateData)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Super Admin updated successfully!',
                'data' => $this->formatUserForApi($user)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update Super Admin via API', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update Super Admin: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete Super Admin (move to trash)
     * DELETE /api/developer/super-admins/{id}
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Authorization check
        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only delete Super Admins you created.'
            ], 403);
        }

        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.'
            ], 422);
        }

        // Check if user created other accounts
        $createdUsersCount = User::where('created_by', $user->id)->count();
        if ($createdUsersCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete Super Admin who has created {$createdUsersCount} user accounts. Move to trash instead.",
                'error' => 'has_dependent_records',
                'dependent_count' => $createdUsersCount
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Store deletion info in metadata
            $metadata = $user->metadata ?? [];
            $metadata['deletion_info'] = [
                'deleted_at' => now()->toISOString(),
                'deleted_by' => auth()->id(),
                'deleted_by_name' => auth()->user()->name,
                'deletion_type' => 'soft_delete',
                'deleted_from' => 'mobile_app'
            ];
            $user->update(['metadata' => $metadata]);

            // Perform soft delete
            $user->delete();

            DB::commit();

            Log::info('Mobile Developer soft deleted Super Admin', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id,
                'super_admin_name' => $user->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Super Admin moved to trash successfully!',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'deleted_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete Super Admin via API', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete Super Admin: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted Super Admin
     * POST /api/developer/super-admins/{id}/restore
     */
    public function restore($id)
    {
        $user = User::onlyTrashed()
            ->where('type', User::TYPE_SUPER_ADMIN)
            ->where('created_by', auth()->id())
            ->findOrFail($id);

        DB::beginTransaction();

        try {
            $user->restore();

            // Update metadata
            $metadata = $user->metadata ?? [];
            $metadata['restored_at'] = now()->toISOString();
            $metadata['restored_by'] = auth()->id();
            $metadata['restored_by_name'] = auth()->user()->name;
            $metadata['restored_from'] = 'mobile_app';
            $user->update(['metadata' => $metadata]);

            DB::commit();

            Log::info('Mobile Developer restored Super Admin from trash', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id,
                'super_admin_name' => $user->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Super Admin restored successfully!',
                'data' => $this->formatUserForApi($user)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore Super Admin via API', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore Super Admin: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permanently delete a soft-deleted Super Admin
     * DELETE /api/developer/super-admins/{id}/force-delete
     */
    public function forceDelete($id)
    {
        $user = User::onlyTrashed()
            ->where('type', User::TYPE_SUPER_ADMIN)
            ->where('created_by', auth()->id())
            ->findOrFail($id);

        // Check if user created other accounts
        $createdUsersCount = User::withTrashed()
            ->where('created_by', $user->id)
            ->where('id', '!=', $user->id)
            ->count();
        
        if ($createdUsersCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot permanently delete Super Admin who created {$createdUsersCount} user accounts. These accounts would become orphaned.",
                'error' => 'has_dependent_records'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Delete invitations
            UserInvitation::where('user_id', $user->id)->delete();

            // Delete user photo if exists
            if ($user->photo && Storage::disk('public')->exists('users/photos/' . $user->photo)) {
                Storage::disk('public')->delete('users/photos/' . $user->photo);
            }

            $userName = $user->name;
            $userEmail = $user->email;

            // Permanently delete the user
            $user->forceDelete();

            DB::commit();

            Log::info('Mobile Developer permanently deleted Super Admin', [
                'developer_id' => auth()->id(),
                'super_admin_name' => $userName,
                'super_admin_email' => $userEmail
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Super Admin permanently deleted successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to permanently delete Super Admin via API', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change Super Admin password
     * POST /api/developer/super-admins/{id}/change-password
     */
    public function changePassword(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'new_password' => 'required|string|min:8|confirmed',
            'force_password_change' => 'sometimes|boolean',
            'notify_user' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user->password = Hash::make($request->new_password);
            
            if ($request->boolean('force_password_change')) {
                $user->force_password_change = true;
            }
            
            $user->save();

            // Send notification if requested
            if ($request->boolean('notify_user')) {
                $this->sendPasswordChangeNotification($user);
            }

            Log::info('Mobile Developer changed Super Admin password', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully!',
                'data' => [
                    'force_password_change' => $user->force_password_change ?? false,
                    'notified' => $request->boolean('notify_user')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to change Super Admin password via API', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to change password: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send invitation to Super Admin
     * POST /api/developer/super-admins/{id}/send-invitation
     */
    public function sendInvitation(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'custom_message' => 'nullable|string|max:1000',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user can receive invitation
        if (!$this->canReceiveInvitation($user)) {
            return response()->json([
                'success' => false,
                'message' => 'User cannot receive invitation (already has active invitation, invalid status, or missing email)',
                'error' => 'cannot_receive_invitation'
            ], 422);
        }

        // Check email configuration
        $emailStatus = $this->getEmailConfigurationStatus();
        if (!$emailStatus['can_send']) {
            return response()->json([
                'success' => false,
                'message' => 'Email configuration is not set up or not working. Please configure email settings first.',
                'error' => 'email_not_configured'
            ], 422);
        }

        try {
            $invitationResult = $this->sendSuperAdminInvitation($user, $request);

            if ($invitationResult['success']) {
                $user->update(['last_invitation_sent_at' => now()]);

                Log::info('Mobile Developer sent invitation to Super Admin', [
                    'developer_id' => auth()->id(),
                    'super_admin_id' => $user->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Invitation sent successfully!',
                    'data' => [
                        'invitation_id' => $invitationResult['invitation_id'],
                        'invitation_url' => $invitationResult['invitation_url'],
                        'expires_in_days' => $request->expires_in_days ?? 7,
                        'sent_at' => now()->toISOString()
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send invitation: ' . $invitationResult['message']
                ], 422);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send invitation via API', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send invitation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resend invitation to Super Admin
     * POST /api/developer/super-admins/{id}/resend-invitation
     */
    public function resendInvitation(Request $request, $id)
    {
        return $this->sendInvitation($request, $id);
    }

    /**
     * Get invitation status for a Super Admin
     * GET /api/developer/super-admins/{id}/invitation-status
     */
    public function getInvitationStatus($id)
    {
        $user = User::with(['invitations' => function($query) {
            $query->latest();
        }])->findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $latestInvitation = $user->invitations->first();
        
        $hasActiveInvitation = $latestInvitation && 
                              in_array($latestInvitation->status, ['sent', 'pending']) && 
                              $latestInvitation->expires_at > now();
        
        $emailStatus = $this->getEmailConfigurationStatus();
        
        return response()->json([
            'success' => true,
            'data' => [
                'has_active_invitation' => $hasActiveInvitation,
                'can_receive_invitation' => !$hasActiveInvitation && !empty($user->email),
                'email_configuration_ready' => $emailStatus['can_send'],
                'email_status' => $emailStatus,
                'latest_invitation' => $latestInvitation ? [
                    'id' => $latestInvitation->id,
                    'sent_at' => $latestInvitation->sent_at?->toISOString(),
                    'expires_at' => $latestInvitation->expires_at?->toISOString(),
                    'status' => $latestInvitation->status,
                    'is_expired' => $latestInvitation->expires_at < now(),
                    'expires_in_hours' => $latestInvitation->expires_at ? $latestInvitation->expires_at->diffInHours(now()) : 0,
                ] : null,
            ]
        ]);
    }

    /**
     * Get invitation details
     * GET /api/developer/super-admins/invitations/{invitationId}
     */
    public function getInvitationDetails($invitationId)
    {
        try {
            $invitation = UserInvitation::with(['user', 'invitedBy'])->findOrFail($invitationId);
            
            if ($invitation->user->created_by !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to invitation details'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatInvitationForApi($invitation)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get invitation details via API', [
                'invitation_id' => $invitationId,
                'developer_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load invitation details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard statistics for developer
     * GET /api/developer/super-admins/dashboard/statistics
     */
    public function dashboardStatistics()
    {
        $developerId = auth()->id();
        
        $statistics = [
            'total_super_admins' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->count(),
            'active_super_admins' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->where('status', User::STATUS_ACTIVE)
                ->count(),
            'pending_super_admins' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->where('status', User::STATUS_PENDING)
                ->count(),
            'suspended_super_admins' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->where('status', User::STATUS_SUSPENDED)
                ->count(),
            'inactive_super_admins' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->where('status', User::STATUS_INACTIVE)
                ->count(),
            'trashed_super_admins' => User::onlyTrashed()
                ->where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->count(),
            'pending_invitations' => UserInvitation::where('invited_by', $developerId)
                ->whereIn('status', ['sent', 'pending'])
                ->where('expires_at', '>', now())
                ->count(),
            'created_today' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->whereDate('created_at', today())
                ->count(),
            'created_this_week' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'email_configured' => $this->getEmailConfigurationStatus()['can_send'],
        ];

        // Get recent Super Admins
        $recentSuperAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
            ->where('created_by', $developerId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'created_at' => $user->created_at->toISOString(),
                    'created_at_formatted' => $user->created_at->diffForHumans()
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'statistics' => $statistics,
                'recent_super_admins' => $recentSuperAdmins
            ]
        ]);
    }

    // ==================== NEW MISSING METHODS ====================

    /**
     * Activate a Super Admin
     * POST /api/developer/super-admins/{id}/activate
     */
    public function activateSuperAdmin($id)
    {
        $user = User::findOrFail($id);

        // Authorization check
        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only activate Super Admins you created.'
            ], 403);
        }

        // Check if user is trashed
        if ($user->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot activate a trashed Super Admin. Please restore first.'
            ], 422);
        }

        // Update status to active
        $user->status = User::STATUS_ACTIVE;
        $user->save();

        // Update metadata
        $metadata = $user->metadata ?? [];
        $metadata['status_changes'][] = [
            'from' => $user->getOriginal('status'),
            'to' => User::STATUS_ACTIVE,
            'changed_by' => auth()->id(),
            'changed_by_name' => auth()->user()->name,
            'changed_at' => now()->toISOString(),
            'action' => 'activate'
        ];
        $user->update(['metadata' => $metadata]);

        Log::info('Mobile Developer activated Super Admin', [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id,
            'previous_status' => $user->getOriginal('status')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Super Admin activated successfully!',
            'data' => $this->formatUserForApi($user)
        ]);
    }

    /**
     * Suspend a Super Admin
     * POST /api/developer/super-admins/{id}/suspend
     */
    public function suspendSuperAdmin($id)
    {
        $user = User::findOrFail($id);

        // Authorization check
        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only suspend Super Admins you created.'
            ], 403);
        }

        // Prevent self-suspension
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot suspend your own account.'
            ], 422);
        }

        // Check if user is trashed
        if ($user->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot suspend a trashed Super Admin.'
            ], 422);
        }

        // Update status to suspended
        $previousStatus = $user->status;
        $user->status = User::STATUS_SUSPENDED;
        $user->save();

        // Update metadata
        $metadata = $user->metadata ?? [];
        $metadata['status_changes'][] = [
            'from' => $previousStatus,
            'to' => User::STATUS_SUSPENDED,
            'changed_by' => auth()->id(),
            'changed_by_name' => auth()->user()->name,
            'changed_at' => now()->toISOString(),
            'action' => 'suspend'
        ];
        $user->update(['metadata' => $metadata]);

        Log::info('Mobile Developer suspended Super Admin', [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id,
            'previous_status' => $previousStatus
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Super Admin suspended successfully!',
            'data' => $this->formatUserForApi($user)
        ]);
    }

    /**
     * Deactivate a Super Admin
     * POST /api/developer/super-admins/{id}/deactivate
     */
    public function deactivateSuperAdmin($id)
    {
        $user = User::findOrFail($id);

        // Authorization check
        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only deactivate Super Admins you created.'
            ], 403);
        }

        // Prevent self-deactivation
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot deactivate your own account.'
            ], 422);
        }

        // Check if user is trashed
        if ($user->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deactivate a trashed Super Admin.'
            ], 422);
        }

        // Update status to inactive
        $previousStatus = $user->status;
        $user->status = User::STATUS_INACTIVE;
        $user->save();

        // Update metadata
        $metadata = $user->metadata ?? [];
        $metadata['status_changes'][] = [
            'from' => $previousStatus,
            'to' => User::STATUS_INACTIVE,
            'changed_by' => auth()->id(),
            'changed_by_name' => auth()->user()->name,
            'changed_at' => now()->toISOString(),
            'action' => 'deactivate'
        ];
        $user->update(['metadata' => $metadata]);

        Log::info('Mobile Developer deactivated Super Admin', [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id,
            'previous_status' => $previousStatus
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Super Admin deactivated successfully!',
            'data' => $this->formatUserForApi($user)
        ]);
    }

    /**
     * Force verify phone number for a Super Admin
     * POST /api/developer/super-admins/{id}/force-verify-phone
     */
    public function forceVerifyPhone($id)
    {
        $user = User::findOrFail($id);

        // Authorization check
        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        // Check if user has a phone number
        if (empty($user->phone)) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have a phone number to verify.'
            ], 422);
        }

        // Set phone verification
        $user->phone_verified_at = now();
        $user->save();

        // Update metadata
        $metadata = $user->metadata ?? [];
        $metadata['phone_verification'][] = [
            'verified_at' => now()->toISOString(),
            'verified_by' => auth()->id(),
            'verified_by_name' => auth()->user()->name,
            'method' => 'forced_by_developer'
        ];
        $user->update(['metadata' => $metadata]);

        Log::info('Mobile Developer force verified phone for Super Admin', [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id,
            'phone' => $user->phone
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Phone number verified successfully!',
            'data' => [
                'phone_verified' => true,
                'phone_verified_at' => $user->phone_verified_at->toISOString()
            ]
        ]);
    }

    /**
     * Remove phone verification for a Super Admin
     * DELETE /api/developer/super-admins/{id}/remove-phone-verification
     */
    public function removePhoneVerification($id)
    {
        $user = User::findOrFail($id);

        // Authorization check
        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        // Remove phone verification
        $user->phone_verified_at = null;
        $user->save();

        // Update metadata
        $metadata = $user->metadata ?? [];
        $metadata['phone_verification'][] = [
            'removed_at' => now()->toISOString(),
            'removed_by' => auth()->id(),
            'removed_by_name' => auth()->user()->name,
            'action' => 'removed_by_developer'
        ];
        $user->update(['metadata' => $metadata]);

        Log::info('Mobile Developer removed phone verification for Super Admin', [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Phone verification removed successfully!',
            'data' => [
                'phone_verified' => false,
                'phone_verified_at' => null
            ]
        ]);
    }

    /**
     * Force verify email for a Super Admin
     * POST /api/developer/super-admins/{id}/force-verify-email
     */
    public function forceVerifyEmail($id)
    {
        $user = User::findOrFail($id);

        // Authorization check
        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        // Check if user has an email
        if (empty($user->email)) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have an email to verify.'
            ], 422);
        }

        // Set email verification
        $user->email_verified_at = now();
        $user->save();

        // Update metadata
        $metadata = $user->metadata ?? [];
        $metadata['email_verification'][] = [
            'verified_at' => now()->toISOString(),
            'verified_by' => auth()->id(),
            'verified_by_name' => auth()->user()->name,
            'method' => 'forced_by_developer'
        ];
        $user->update(['metadata' => $metadata]);

        Log::info('Mobile Developer force verified email for Super Admin', [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id,
            'email' => $user->email
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully!',
            'data' => [
                'email_verified' => true,
                'email_verified_at' => $user->email_verified_at->toISOString()
            ]
        ]);
    }

    /**
     * Remove email verification for a Super Admin
     * DELETE /api/developer/super-admins/{id}/remove-email-verification
     */
    public function removeEmailVerification($id)
    {
        $user = User::findOrFail($id);

        // Authorization check
        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        // Remove email verification
        $user->email_verified_at = null;
        $user->save();

        // Update metadata
        $metadata = $user->metadata ?? [];
        $metadata['email_verification'][] = [
            'removed_at' => now()->toISOString(),
            'removed_by' => auth()->id(),
            'removed_by_name' => auth()->user()->name,
            'action' => 'removed_by_developer'
        ];
        $user->update(['metadata' => $metadata]);

        Log::info('Mobile Developer removed email verification for Super Admin', [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email verification removed successfully!',
            'data' => [
                'email_verified' => false,
                'email_verified_at' => null
            ]
        ]);
    }

    /**
     * Get invitation history for a Super Admin
     * GET /api/developer/super-admins/{id}/invitation-history
     */
    public function invitationHistory($id)
    {
        $user = User::findOrFail($id);

        // Authorization check
        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $invitations = UserInvitation::where('user_id', $user->id)
            ->with('invitedBy')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $invitations->getCollection()->transform(function ($invitation) {
            return $this->formatInvitationForApi($invitation);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'invitations' => $invitations,
                'statistics' => $this->getInvitationStats($user)
            ]
        ]);
    }

    /**
     * Get user activities for a Super Admin
     * GET /api/developer/super-admins/{id}/activities
     */
    public function activities($id, Request $request)
    {
        $user = User::findOrFail($id);

        // Authorization check
        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
            'type' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get activities from metadata if no dedicated activity log exists
        $activities = [];

        // Status changes
        if (isset($user->metadata['status_changes'])) {
            foreach ($user->metadata['status_changes'] as $change) {
                $activities[] = [
                    'type' => 'status_change',
                    'description' => "Status changed from {$change['from']} to {$change['to']}",
                    'performed_by' => $change['changed_by_name'],
                    'performed_at' => $change['changed_at'],
                    'details' => $change
                ];
            }
        }

        // Email verification changes
        if (isset($user->metadata['email_verification'])) {
            foreach ($user->metadata['email_verification'] as $change) {
                $type = isset($change['verified_at']) ? 'email_verified' : 'email_verification_removed';
                $activities[] = [
                    'type' => $type,
                    'description' => isset($change['verified_at']) ? 'Email verified by developer' : 'Email verification removed by developer',
                    'performed_by' => $change['verified_by_name'] ?? $change['removed_by_name'] ?? 'System',
                    'performed_at' => $change['verified_at'] ?? $change['removed_at'],
                    'details' => $change
                ];
            }
        }

        // Phone verification changes
        if (isset($user->metadata['phone_verification'])) {
            foreach ($user->metadata['phone_verification'] as $change) {
                $type = isset($change['verified_at']) ? 'phone_verified' : 'phone_verification_removed';
                $activities[] = [
                    'type' => $type,
                    'description' => isset($change['verified_at']) ? 'Phone verified by developer' : 'Phone verification removed by developer',
                    'performed_by' => $change['verified_by_name'] ?? $change['removed_by_name'] ?? 'System',
                    'performed_at' => $change['verified_at'] ?? $change['removed_at'],
                    'details' => $change
                ];
            }
        }

        // Sort activities by performed_at descending
        usort($activities, function($a, $b) {
            return strtotime($b['performed_at']) - strtotime($a['performed_at']);
        });

        // Apply date filters
        if ($request->has('date_from') && !empty($request->date_from)) {
            $activities = array_filter($activities, function($activity) use ($request) {
                return strtotime($activity['performed_at']) >= strtotime($request->date_from);
            });
        }

        if ($request->has('date_to') && !empty($request->date_to)) {
            $activities = array_filter($activities, function($activity) use ($request) {
                return strtotime($activity['performed_at']) <= strtotime($request->date_to . ' 23:59:59');
            });
        }

        // Apply type filter
        if ($request->has('type') && !empty($request->type)) {
            $activities = array_filter($activities, function($activity) use ($request) {
                return $activity['type'] === $request->type;
            });
        }

        // Paginate activities
        $perPage = $request->get('per_page', 20);
        $currentPage = $request->get('page', 1);
        $activities = array_values($activities); // Re-index
        $total = count($activities);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedActivities = array_slice($activities, $offset, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'activities' => $paginatedActivities,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $currentPage,
                    'last_page' => ceil($total / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total)
                ]
            ]
        ]);
    }

    /**
     * Export activities to CSV
     * GET /api/developer/super-admins/{id}/activities/export
     */
    public function exportActivities($id, Request $request)
    {
        $user = User::findOrFail($id);

        // Authorization check
        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        // Get activities using the same method as above
        $activities = $this->getAllActivitiesArray($user);

        // Apply filters if provided
        if ($request->has('date_from') && !empty($request->date_from)) {
            $activities = array_filter($activities, function($activity) use ($request) {
                return strtotime($activity['performed_at']) >= strtotime($request->date_from);
            });
        }

        if ($request->has('date_to') && !empty($request->date_to)) {
            $activities = array_filter($activities, function($activity) use ($request) {
                return strtotime($activity['performed_at']) <= strtotime($request->date_to . ' 23:59:59');
            });
        }

        if ($request->has('type') && !empty($request->type)) {
            $activities = array_filter($activities, function($activity) use ($request) {
                return $activity['type'] === $request->type;
            });
        }

        // Generate CSV
        $filename = "super_admin_{$user->id}_activities_" . date('Y-m-d_His') . ".csv";
        $handle = fopen('php://temp', 'w');

        // Add CSV headers
        fputcsv($handle, ['Type', 'Description', 'Performed By', 'Performed At', 'Details']);

        // Add activity rows
        foreach ($activities as $activity) {
            fputcsv($handle, [
                $activity['type'],
                $activity['description'],
                $activity['performed_by'],
                $activity['performed_at'],
                json_encode($activity['details'])
            ]);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        // Store CSV temporarily and return download response
        $tempPath = storage_path("app/temp/{$filename}");
        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        file_put_contents($tempPath, $csvContent);

        Log::info('Mobile Developer exported activities for Super Admin', [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id,
            'activity_count' => count($activities)
        ]);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Get all activities as array (helper for export)
     */
    private function getAllActivitiesArray(User $user): array
    {
        $activities = [];

        // Status changes
        if (isset($user->metadata['status_changes'])) {
            foreach ($user->metadata['status_changes'] as $change) {
                $activities[] = [
                    'type' => 'status_change',
                    'description' => "Status changed from {$change['from']} to {$change['to']}",
                    'performed_by' => $change['changed_by_name'] ?? 'System',
                    'performed_at' => $change['changed_at'],
                    'details' => $change
                ];
            }
        }

        // Email verification changes
        if (isset($user->metadata['email_verification'])) {
            foreach ($user->metadata['email_verification'] as $change) {
                $type = isset($change['verified_at']) ? 'email_verified' : 'email_verification_removed';
                $activities[] = [
                    'type' => $type,
                    'description' => isset($change['verified_at']) ? 'Email verified by developer' : 'Email verification removed by developer',
                    'performed_by' => $change['verified_by_name'] ?? $change['removed_by_name'] ?? 'System',
                    'performed_at' => $change['verified_at'] ?? $change['removed_at'],
                    'details' => $change
                ];
            }
        }

        // Phone verification changes
        if (isset($user->metadata['phone_verification'])) {
            foreach ($user->metadata['phone_verification'] as $change) {
                $type = isset($change['verified_at']) ? 'phone_verified' : 'phone_verification_removed';
                $activities[] = [
                    'type' => $type,
                    'description' => isset($change['verified_at']) ? 'Phone verified by developer' : 'Phone verification removed by developer',
                    'performed_by' => $change['verified_by_name'] ?? $change['removed_by_name'] ?? 'System',
                    'performed_at' => $change['verified_at'] ?? $change['removed_at'],
                    'details' => $change
                ];
            }
        }

        // Sort by performed_at descending
        usort($activities, function($a, $b) {
            return strtotime($b['performed_at']) - strtotime($a['performed_at']);
        });

        return $activities;
    }

    /**
     * Bulk actions on Super Admins
     * POST /api/developer/super-admins/bulk-actions
     */
    public function bulkAction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:activate,suspend,deactivate,delete,restore,force_delete,send_invitation',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
            'confirmation' => 'required_if:action,force_delete|nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Force delete confirmation check
        if ($request->action === 'force_delete' && $request->confirmation !== 'PERMANENT_DELETE') {
            return response()->json([
                'success' => false,
                'message' => 'Please type "PERMANENT_DELETE" to confirm permanent deletion.'
            ], 422);
        }

        // Email configuration check for invitations
        if ($request->action === 'send_invitation') {
            $emailStatus = $this->getEmailConfigurationStatus();
            if (!$emailStatus['can_send']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email configuration is not set up or not working.'
                ], 422);
            }
        }

        $results = [
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($request->user_ids as $userId) {
            try {
                $user = User::withTrashed()->find($userId);

                if (!$user) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => false,
                        'message' => 'User not found'
                    ];
                    continue;
                }

                // Authorization check
                if ($user->created_by !== auth()->id()) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => false,
                        'message' => 'Unauthorized'
                    ];
                    continue;
                }

                // Prevent self actions
                if (in_array($request->action, ['suspend', 'deactivate', 'delete']) && $user->id === auth()->id()) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => false,
                        'message' => 'Cannot perform action on your own account'
                    ];
                    continue;
                }

                $actionResult = $this->executeBulkAction($request->action, $user, $request);
                
                if ($actionResult['success']) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
                
                $results['details'][] = [
                    'id' => $userId,
                    'success' => $actionResult['success'],
                    'message' => $actionResult['message']
                ];

            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'id' => $userId,
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => $results['success'] > 0,
            'data' => $results,
            'message' => "Completed: {$results['success']} successful, {$results['failed']} failed"
        ]);
    }

    /**
     * Get available channels for invitation (email/SMS/etc)
     * GET /api/developer/super-admins/{id}/available-channels
     */
    public function getAvailableChannels($id)
    {
        $user = User::findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $emailStatus = $this->getEmailConfigurationStatus();
        
        $channels = [];
        
        if (!empty($user->email) && $emailStatus['can_send']) {
            $channels['email'] = [
                'name' => 'Email',
                'available' => true,
                'address' => $user->email,
                'status' => 'available',
                'icon' => 'email'
            ];
        } elseif (!empty($user->email)) {
            $channels['email'] = [
                'name' => 'Email',
                'available' => false,
                'address' => $user->email,
                'status' => 'email_not_configured',
                'icon' => 'email',
                'reason' => 'Email configuration incomplete'
            ];
        }

        // SMS channel (if implemented)
        if (!empty($user->phone)) {
            $channels['sms'] = [
                'name' => 'SMS',
                'available' => false, // Set to true when SMS is implemented
                'address' => $user->phone,
                'status' => 'not_implemented',
                'icon' => 'sms',
                'reason' => 'SMS notifications not implemented yet'
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'channels' => $channels,
                'has_email' => !empty($user->email),
                'email_status' => $emailStatus
            ]
        ]);
    }

    /**
     * Check user relations before deletion
     * GET /api/developer/super-admins/{id}/check-relations
     */
    public function checkRelations($id)
    {
        $user = User::withTrashed()->findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $criticalRelations = [];

        $createdUsersCount = User::withTrashed()->where('created_by', $user->id)->where('id', '!=', $user->id)->count();
        if ($createdUsersCount > 0) {
            $criticalRelations[] = [
                'type' => 'created_users',
                'count' => $createdUsersCount,
                'description' => "{$createdUsersCount} user account(s) they created"
            ];
        }

        $activeInvitations = UserInvitation::where('user_id', $user->id)
            ->whereIn('status', ['sent', 'pending'])
            ->where('expires_at', '>', now())
            ->count();
        if ($activeInvitations > 0) {
            $criticalRelations[] = [
                'type' => 'active_invitations',
                'count' => $activeInvitations,
                'description' => "{$activeInvitations} active invitation(s)"
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'has_critical_relations' => !empty($criticalRelations),
                'critical_relations' => $criticalRelations,
                'can_be_deleted' => empty($criticalRelations),
                'can_be_permanently_deleted' => $this->canBePermanentlyDeleted($user),
                'is_trashed' => $user->trashed(),
                'message' => empty($criticalRelations) 
                    ? 'User can be safely deleted' 
                    : 'User has critical relations that may prevent deletion'
            ]
        ]);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Format user for API response
     */
    private function formatUserForApi(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'phone_local' => $this->getLocalPhone($user->phone),
            'phone_verified' => !is_null($user->phone_verified_at),
            'phone_verified_at' => $user->phone_verified_at ? $user->phone_verified_at->toISOString() : null,
            'email_verified' => !is_null($user->email_verified_at),
            'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toISOString() : null,
            'status' => $user->status,
            'status_label' => $this->getStatusLabel($user->status),
            'type' => $user->type,
            'username' => $user->username,
            'gender' => $user->gender,
            'digital_address' => $user->digital_address,
            'region' => $user->region,
            'location' => $user->location,
            'photo_url' => $user->photo ? Storage::disk('public')->url('users/photos/' . $user->photo) : null,
            'has_photo' => !empty($user->photo),
            'initials' => $this->getUserInitials($user->name, $user->email),
            'login_count' => $user->login_count,
            'last_login_at' => $user->last_login_at ? $user->last_login_at->toISOString() : null,
            'last_login_ip' => $user->last_login_ip,
            'last_invitation_sent_at' => $user->last_invitation_sent_at ? $user->last_invitation_sent_at->toISOString() : null,
            'created_at' => $user->created_at->toISOString(),
            'created_at_formatted' => $user->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $user->created_at->diffForHumans(),
            'updated_at' => $user->updated_at ? $user->updated_at->toISOString() : null,
            'is_trashed' => $user->trashed(),
            'creator' => $user->creator ? [
                'id' => $user->creator->id,
                'name' => $user->creator->name,
                'email' => $user->creator->email
            ] : null,
        ];
    }

    /**
     * Format invitation for API response
     */
    private function formatInvitationForApi(UserInvitation $invitation): array
    {
        $statusColors = [
            'sent' => 'warning',
            'accepted' => 'success',
            'expired' => 'danger',
            'failed' => 'danger',
            'cancelled' => 'secondary',
            'pending' => 'warning',
        ];

        $statusLabels = [
            'sent' => 'Sent',
            'accepted' => 'Accepted',
            'expired' => 'Expired',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'pending' => 'Pending',
        ];

        return [
            'id' => $invitation->id,
            'status' => $invitation->status,
            'status_color' => $statusColors[$invitation->status] ?? 'secondary',
            'status_label' => $statusLabels[$invitation->status] ?? ucfirst($invitation->status),
            'created_at' => $invitation->created_at ? $invitation->created_at->toISOString() : null,
            'created_at_formatted' => $invitation->created_at ? $invitation->created_at->format('Y-m-d H:i:s') : null,
            'expires_at' => $invitation->expires_at ? $invitation->expires_at->toISOString() : null,
            'expires_at_formatted' => $invitation->expires_at ? $invitation->expires_at->format('Y-m-d H:i:s') : null,
            'is_expired' => $invitation->expires_at ? $invitation->expires_at->isPast() : true,
            'sent_at' => $invitation->sent_at ? $invitation->sent_at->toISOString() : null,
            'invitation_url' => $invitation->token ? route('invitation.accept', ['token' => $invitation->token]) : null,
            'custom_message' => $invitation->custom_message,
            'failure_reason' => $invitation->failure_reason,
            'send_attempts' => $invitation->send_attempts ?? 0,
            'invited_by' => $invitation->invitedBy ? [
                'id' => $invitation->invitedBy->id,
                'name' => $invitation->invitedBy->name,
            ] : null,
        ];
    }

    /**
     * Get statistics for dashboard
     */
    private function getStatistics(): array
    {
        $developerId = auth()->id();
        
        return [
            'total' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->count(),
            'active' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->where('status', User::STATUS_ACTIVE)
                ->count(),
            'pending' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->where('status', User::STATUS_PENDING)
                ->count(),
            'suspended' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->where('status', User::STATUS_SUSPENDED)
                ->count(),
            'inactive' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->where('status', User::STATUS_INACTIVE)
                ->count(),
            'trashed' => User::onlyTrashed()
                ->where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->count(),
        ];
    }

    /**
     * Get invitation statistics for a user
     */
    private function getInvitationStats(User $user): array
    {
        $invitations = $user->invitations;
        
        return [
            'total' => $invitations->count(),
            'sent' => $invitations->where('status', 'sent')->count(),
            'failed' => $invitations->where('status', 'failed')->count(),
            'expired' => $invitations->where('status', 'expired')->count(),
            'accepted' => $invitations->where('status', 'accepted')->count(),
            'cancelled' => $invitations->where('status', 'cancelled')->count(),
            'last_sent' => $user->last_invitation_sent_at ? $user->last_invitation_sent_at->diffForHumans() : 'Never',
        ];
    }

    /**
     * Get recent activities for a user
     */
    private function getRecentActivities(User $user): array
    {
        if (!class_exists(\App\Models\UserActivity::class)) {
            return [];
        }
        
        return \App\Models\UserActivity::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($activity) {
                return [
                    'description' => $activity->description,
                    'created_at' => $activity->created_at->toISOString(),
                    'created_at_formatted' => $activity->created_at->format('Y-m-d H:i:s'),
                    'time_ago' => $activity->created_at->diffForHumans(),
                    'ip_address' => $activity->ip_address,
                    'user_agent' => $activity->user_agent,
                ];
            })
            ->toArray();
    }

    /**
     * Get email configuration status
     */
    private function getEmailConfigurationStatus(): array
    {
        try {
            return $this->developerEmailService->checkConfigurationStatus();
        } catch (\Exception $e) {
            Log::error('Failed to get email configuration status: ' . $e->getMessage());
            return [
                'configured' => false,
                'connection' => false,
                'authentication' => false,
                'can_send' => false,
                'message' => 'Email configuration check failed',
                'last_test' => null,
            ];
        }
    }

    /**
     * Send super admin invitation
     */
    private function sendSuperAdminInvitation(User $user, Request $request, $preHashedPassword = null): array
    {
        try {
            $expiresInDays = (int) ($request->expires_in_days ?? 7);
            $expiresInDays = max(1, min(30, $expiresInDays));

            $token = Str::random(60);
            $temporaryPassword = $preHashedPassword ? null : Str::random(32);
            $passwordHash = $preHashedPassword ?? Hash::make($temporaryPassword);

            $invitation = UserInvitation::create([
                'user_id' => $user->id,
                'invited_by' => auth()->id(),
                'custom_message' => $request->custom_message ?? null,
                'expires_at' => now()->addDays($expiresInDays),
                'token' => $token,
                'status' => UserInvitation::STATUS_PENDING,
                'metadata' => [
                    'temporary_password_hash' => $passwordHash,
                    'expires_in_days' => $expiresInDays,
                    'created_via' => 'mobile_app'
                ]
            ]);

            // Send email
            $emailResult = $this->sendInvitationEmail($user, $invitation, $request);

            if ($emailResult['success']) {
                $invitation->update([
                    'status' => UserInvitation::STATUS_SENT,
                    'sent_at' => now(),
                    'send_attempts' => ($invitation->send_attempts ?? 0) + 1
                ]);
                
                // Update user metadata
                $metadata = $user->metadata ?? [];
                $invitationHistory = $metadata['invitation_history'] ?? [];
                $invitationHistory[] = [
                    'invitation_id' => $invitation->id,
                    'sent_at' => now()->toISOString(),
                    'channel' => 'email',
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'expires_in_days' => $expiresInDays,
                    'source' => 'mobile_app'
                ];
                $metadata['invitation_history'] = $invitationHistory;
                $user->update(['metadata' => $metadata]);
            } else {
                $invitation->update([
                    'status' => UserInvitation::STATUS_FAILED,
                    'failed_at' => now(),
                    'failure_reason' => 'Email sending failed: ' . substr($emailResult['message'], 0, 255),
                    'send_attempts' => ($invitation->send_attempts ?? 0) + 1
                ]);
            }

            return [
                'success' => $emailResult['success'],
                'message' => $emailResult['message'],
                'invitation_id' => $invitation->id,
                'invitation_url' => route('invitation.accept', ['token' => $invitation->token]),
                'expires_in_days' => $expiresInDays
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send super admin invitation: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send invitation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send invitation email
     */
    private function sendInvitationEmail(User $user, UserInvitation $invitation, Request $request): array
    {
        try {
            $invitationUrl = route('invitation.accept', ['token' => $invitation->token]);
            $expiryDate = $invitation->expires_at->format('F j, Y \a\t g:i A');
            $developerName = auth()->user()->name ?? 'System Developer';
            $appName = config('app.name', 'Application');

            if (class_exists(\App\Mail\SuperAdminInvitationMail::class)) {
                \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\SuperAdminInvitationMail(
                    $user,
                    $invitation,
                    $appName,
                    $developerName,
                    $expiryDate,
                    $invitationUrl
                ));
            } else {
                // Send using mailer configuration
                $this->sendRawInvitationEmail($user, $invitation, $invitationUrl, $expiryDate, $developerName, $appName);
            }

            return [
                'success' => true,
                'message' => 'Invitation email sent successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Send raw invitation email (fallback)
     */
    private function sendRawInvitationEmail(User $user, UserInvitation $invitation, string $invitationUrl, string $expiryDate, string $developerName, string $appName): void
    {
        $subject = "Super Admin Invitation - {$appName}";
        
        $htmlContent = $this->generateInvitationEmailHtml($user, $invitationUrl, $expiryDate, $developerName, $appName);
        
        \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($user, $subject, $htmlContent) {
            $message->to($user->email)
                    ->subject($subject)
                    ->html($htmlContent);
        });
    }

    /**
     * Generate invitation email HTML
     */
    private function generateInvitationEmailHtml(User $user, string $invitationUrl, string $expiryDate, string $developerName, string $appName): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Super Admin Invitation - {$appName}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4a6ee0; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .button { background: #4a6ee0; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
        .footer { font-size: 12px; color: #666; text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Super Admin Invitation</h1>
        </div>
        <div class="content">
            <h2>Hello {$user->name},</h2>
            <p>You have been invited to become a Super Administrator for <strong>{$appName}</strong> by <strong>{$developerName}</strong>.</p>
            
            <p><strong>Invitation Details:</strong></p>
            <ul>
                <li>Expires: {$expiryDate}</li>
                <li>Role: Super Administrator</li>
            </ul>
            
            <p style="text-align: center;">
                <a href="{$invitationUrl}" class="button">Accept Invitation</a>
            </p>
            
            <p>Or copy this link: <br>{$invitationUrl}</p>
            
            <p>This invitation will expire on {$expiryDate}.</p>
        </div>
        <div class="footer">
            <p>&copy; {$appName}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Send password change notification
     */
    private function sendPasswordChangeNotification(User $user): void
    {
        try {
            $appName = config('app.name', 'Application');
            $developerName = auth()->user()->name;
            $timestamp = now()->format('F j, Y \a\t g:i A');
            
            $subject = "Password Changed - {$appName}";
            
            $htmlContent = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password Changed - {$appName}</title>
</head>
<body>
    <h2>Password Change Notification</h2>
    <p>Hello {$user->name},</p>
    <p>Your password has been changed by <strong>{$developerName}</strong> on {$timestamp}.</p>
    <p>If you did not authorize this change, please contact support immediately.</p>
    <p>Best regards,<br>{$appName} Team</p>
</body>
</html>
HTML;
            
            \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($user, $subject, $htmlContent) {
                $message->to($user->email)
                        ->subject($subject)
                        ->html($htmlContent);
            });

        } catch (\Exception $e) {
            Log::warning("Failed to send password change notification: " . $e->getMessage(), [
                'user_id' => $user->id
            ]);
        }
    }

    /**
     * Check if user can receive invitation
     */
    private function canReceiveInvitation(User $user): bool
    {
        $validStatus = in_array($user->status, [User::STATUS_PENDING, User::STATUS_ACTIVE]);
        $hasEmail = !empty($user->email);
        
        $hasActiveInvitation = UserInvitation::where('user_id', $user->id)
            ->whereIn('status', ['sent', 'pending'])
            ->where('expires_at', '>', now())
            ->exists();

        return $validStatus && $hasEmail && !$hasActiveInvitation;
    }

    /**
     * Check if user has active invitation
     */
    private function hasActiveInvitation(User $user): bool
    {
        return UserInvitation::where('user_id', $user->id)
            ->whereIn('status', ['sent', 'pending'])
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Check if user can be permanently deleted
     */
    private function canBePermanentlyDeleted(User $user): bool
    {
        $createdUsersCount = User::withTrashed()
            ->where('created_by', $user->id)
            ->where('id', '!=', $user->id)
            ->count();
        
        return $createdUsersCount === 0;
    }

    /**
     * Execute bulk action for a single user
     */
    private function executeBulkAction(string $action, User $user, Request $request): array
    {
        switch ($action) {
            case 'activate':
                if (!$user->trashed()) {
                    $user->update(['status' => User::STATUS_ACTIVE]);
                    return ['success' => true, 'message' => 'Activated'];
                }
                return ['success' => false, 'message' => 'Cannot activate trashed user'];

            case 'suspend':
                if (!$user->trashed()) {
                    $user->update(['status' => User::STATUS_SUSPENDED]);
                    return ['success' => true, 'message' => 'Suspended'];
                }
                return ['success' => false, 'message' => 'Cannot suspend trashed user'];

            case 'deactivate':
                if (!$user->trashed()) {
                    $user->update(['status' => User::STATUS_INACTIVE]);
                    return ['success' => true, 'message' => 'Deactivated'];
                }
                return ['success' => false, 'message' => 'Cannot deactivate trashed user'];

            case 'delete':
                if (!$user->trashed()) {
                    $createdUsersCount = User::withTrashed()->where('created_by', $user->id)->count();
                    if ($createdUsersCount === 0) {
                        $metadata = $user->metadata ?? [];
                        $metadata['deletion_info'] = [
                            'deleted_at' => now()->toISOString(),
                            'deleted_by' => auth()->id(),
                            'deleted_by_name' => auth()->user()->name,
                            'deletion_type' => 'bulk_soft_delete'
                        ];
                        $user->update(['metadata' => $metadata]);
                        $user->delete();
                        return ['success' => true, 'message' => 'Moved to trash'];
                    }
                    return ['success' => false, 'message' => "Created {$createdUsersCount} user accounts"];
                }
                return ['success' => false, 'message' => 'Already in trash'];

            case 'restore':
                if ($user->trashed()) {
                    $user->restore();
                    return ['success' => true, 'message' => 'Restored'];
                }
                return ['success' => false, 'message' => 'Not in trash'];

            case 'force_delete':
                if ($user->trashed()) {
                    $createdUsersCount = User::withTrashed()->where('created_by', $user->id)->where('id', '!=', $user->id)->count();
                    if ($createdUsersCount === 0) {
                        UserInvitation::where('user_id', $user->id)->delete();
                        if ($user->photo && Storage::disk('public')->exists('users/photos/' . $user->photo)) {
                            Storage::disk('public')->delete('users/photos/' . $user->photo);
                        }
                        $user->forceDelete();
                        return ['success' => true, 'message' => 'Permanently deleted'];
                    }
                    return ['success' => false, 'message' => "Created {$createdUsersCount} user accounts"];
                }
                return ['success' => false, 'message' => 'Not in trash'];

            case 'send_invitation':
                if (!$user->trashed() && $this->canReceiveInvitation($user)) {
                    $invitationRequest = new \Illuminate\Http\Request([
                        'expires_in_days' => $request->expires_in_days ?? 7,
                    ]);
                    $result = $this->sendSuperAdminInvitation($user, $invitationRequest);
                    if ($result['success']) {
                        $user->update(['last_invitation_sent_at' => now()]);
                        return ['success' => true, 'message' => 'Invitation sent'];
                    }
                    return ['success' => false, 'message' => $result['message']];
                }
                return ['success' => false, 'message' => 'Cannot send invitation'];

            default:
                return ['success' => false, 'message' => 'Unknown action'];
        }
    }

    /**
     * Upload base64 photo
     */
    private function uploadBase64Photo(string $base64String): ?string
    {
        try {
            // Decode base64
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64String));
            
            if (!$imageData) {
                return null;
            }
            
            // Generate unique filename
            $filename = 'super_admin_' . Str::random(40) . '.jpg';
            $path = 'users/photos/' . $filename;
            
            // Store file
            Storage::disk('public')->put($path, $imageData);
            
            return $filename;
        } catch (\Exception $e) {
            Log::error('Failed to upload base64 photo: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get oldest trashed days
     */
    private function getOldestTrashedDays(): ?int
    {
        $oldest = User::onlyTrashed()
            ->where('type', User::TYPE_SUPER_ADMIN)
            ->where('created_by', auth()->id())
            ->orderBy('deleted_at', 'asc')
            ->first();
        
        return $oldest && $oldest->deleted_at ? $oldest->deleted_at->diffInDays(now()) : null;
    }

    /**
     * Get status label
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            User::STATUS_PENDING => 'Pending',
            User::STATUS_ACTIVE => 'Active',
            User::STATUS_SUSPENDED => 'Suspended',
            User::STATUS_INACTIVE => 'Inactive',
        ];
        
        return $labels[$status] ?? 'Unknown';
    }

    /**
     * Get local phone format
     */
    private function getLocalPhone(?string $phone): string
    {
        if (!$phone) {
            return '';
        }

        if (preg_match('/^\+233(\d{9})$/', $phone, $matches)) {
            return '0' . $matches[1];
        }

        return $phone;
    }

    /**
     * Get user initials
     */
    private function getUserInitials(string $name, ?string $email): string
    {
        $names = explode(' ', trim($name));
        $initials = '';
        
        if (count($names) >= 2) {
            $initials = strtoupper(substr($names[0], 0, 1) . substr($names[count($names) - 1], 0, 1));
        } elseif (count($names) === 1) {
            $initials = strtoupper(substr($names[0], 0, 2));
        } else {
            $initials = strtoupper(substr($email ?? 'SA', 0, 2));
        }
        
        return $initials;
    }

    /**
     * Optimize database queries with eager loading
     */
    protected function getOptimizedUserQuery()
    {
        return User::where('type', User::TYPE_SUPER_ADMIN)
            ->where('created_by', auth()->id())
            ->with(['creator:id,name,email'])
            ->select(['id', 'name', 'email', 'phone', 'status', 'created_at', 'updated_at']);
    }

    /**
     * Cache user data for 5 minutes to reduce database load
     */
    protected function getCachedUserData($userId)
    {
        $cacheKey = "super_admin_{$userId}";
        
        return cache()->remember($cacheKey, now()->addMinutes(5), function () use ($userId) {
            return User::with(['creator'])->findOrFail($userId);
        });
    }

    /**
     * Clear cache on user update
     */
    protected function clearUserCache($userId)
    {
        cache()->forget("super_admin_{$userId}");
    }
}
<?php

namespace App\Http\Controllers\Developer;

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
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class DeveloperSuperAdminController extends Controller
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
     * Display a listing of super admin users
     */
    public function index(Request $request)
    {
        $query = User::where('type', User::TYPE_SUPER_ADMIN)
            ->where('created_by', auth()->id())
            ->with(['creator'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('status') && $request->status != 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('digital_address', 'like', "%{$search}%");
            });
        }

        // Date range filter
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $superAdmins = $query->paginate($request->get('per_page', 20));

        // Add display data
        $superAdmins->getCollection()->transform(function ($user) {
            return $this->prepareUserForDisplay($user);
        });

        $statuses = [
            User::STATUS_PENDING => 'Pending',
            User::STATUS_ACTIVE => 'Active',
            User::STATUS_SUSPENDED => 'Suspended',
            User::STATUS_INACTIVE => 'Inactive',
        ];

        $emailConfigStatus = $this->getEmailConfigurationStatus();
        $statistics = $this->getStatistics();

        if ($request->has('export') && $request->export == 'csv') {
            return $this->exportSuperAdmins($superAdmins);
        }

        return view('developer.super-admins.index', compact(
            'superAdmins', 
            'statuses',
            'emailConfigStatus',
            'statistics'
        ));
    }

   /**
 * Display trashed (soft-deleted) super admins
 */
public function trash(Request $request)
{
    $query = User::onlyTrashed()
        ->where('type', User::TYPE_SUPER_ADMIN)
        ->where('created_by', auth()->id())
        ->with(['creator'])  // REMOVED 'deletedBy' from here
        ->orderBy('deleted_at', 'desc');

    // Apply filters
    if ($request->has('search') && !empty($request->search)) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    if ($request->has('date_from') && !empty($request->date_from)) {
        $query->whereDate('deleted_at', '>=', $request->date_from);
    }
    
    if ($request->has('date_to') && !empty($request->date_to)) {
        $query->whereDate('deleted_at', '<=', $request->date_to);
    }

    $trashedSuperAdmins = $query->paginate($request->get('per_page', 20));

    // Add display data
    $trashedSuperAdmins->getCollection()->transform(function ($user) {
        $user = $this->prepareUserForDisplay($user);
        $user->deleted_at_formatted = $user->deleted_at ? $user->deleted_at->format('Y-m-d H:i:s') : 'N/A';
        $user->deleted_by_name = 'System'; // REMOVED deletedBy relationship, use default
        $user->days_in_trash = $user->deleted_at ? $user->deleted_at->diffInDays(now()) : 0;
        return $user;
    });

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
        'oldest_trashed' => User::onlyTrashed()
            ->where('type', User::TYPE_SUPER_ADMIN)
            ->where('created_by', auth()->id())
            ->orderBy('deleted_at', 'asc')
            ->first(),
    ];

    return view('developer.super-admins.trash', compact('trashedSuperAdmins', 'statistics'));
}

    /**
     * Restore a soft-deleted super admin
     */
    /**
 * Restore a soft-deleted super admin
 */
public function restore($id)
{
    $user = User::onlyTrashed()
        ->where('type', User::TYPE_SUPER_ADMIN)
        ->where('created_by', auth()->id())
        ->findOrFail($id);

    DB::beginTransaction();

    try {
        // Restore the user
        $user->restore();
        
        // REMOVED: Invitations don't have soft deletes, so no need to restore them
        // UserInvitation records remain as they were (not soft deleted)
        // If you want to reactivate expired invitations, handle that separately

        // Update metadata
        $metadata = $user->metadata ?? [];
        $metadata['restored_at'] = now()->toISOString();
        $metadata['restored_by'] = auth()->id();
        $metadata['restored_by_name'] = auth()->user()->name;
        $user->update(['metadata' => $metadata]);

        DB::commit();

        Log::info('Developer restored Super Admin from trash', [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id,
            'super_admin_name' => $user->name
        ]);

        return redirect()->route('developer.super-admins.show', $user->id)
            ->with('success', 'Super Admin account restored successfully!');

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to restore Super Admin', [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id,
            'error' => $e->getMessage()
        ]);

        return back()->with('error', 'Failed to restore Super Admin: ' . $e->getMessage());
    }
}

/**
 * Permanently delete a soft-deleted super admin
 */
public function forceDelete($id)
{
    $user = User::onlyTrashed()
        ->where('type', User::TYPE_SUPER_ADMIN)
        ->where('created_by', auth()->id())
        ->findOrFail($id);

    DB::beginTransaction();

    try {
        // Check if user created other accounts (prevent orphaned data)
        $createdUsersCount = User::withTrashed()
            ->where('created_by', $user->id)
            ->where('id', '!=', $user->id)
            ->count();
        
        if ($createdUsersCount > 0) {
            throw new \Exception("Cannot permanently delete Super Admin who created {$createdUsersCount} user accounts. These accounts would become orphaned.");
        }

        // FIXED: Use delete() instead of forceDelete() since UserInvitation doesn't have SoftDeletes
        // This will permanently remove the invitation records
        UserInvitation::where('user_id', $user->id)->delete();

        // Delete user photo if exists
        if ($user->photo && Storage::disk('public')->exists('users/photos/' . $user->photo)) {
            Storage::disk('public')->delete('users/photos/' . $user->photo);
        }

        // Store info for logging before force delete
        $userName = $user->name;
        $userEmail = $user->email;

        // Permanently delete the user
        $user->forceDelete();

        DB::commit();

        Log::info('Developer permanently deleted Super Admin', [
            'developer_id' => auth()->id(),
            'super_admin_name' => $userName,
            'super_admin_email' => $userEmail
        ]);

        return redirect()->route('developer.super-admins.trash')
            ->with('success', 'Super Admin permanently deleted successfully!');

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to permanently delete Super Admin', [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id,
            'error' => $e->getMessage()
        ]);

        return back()->with('error', 'Failed to permanently delete: ' . $e->getMessage());
    }
}

/**
 * Bulk restore multiple soft-deleted super admins
 */
public function bulkRestore(Request $request)
{
    $validator = Validator::make($request->all(), [
        'user_ids' => 'required|array',
        'user_ids.*' => 'exists:users,id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    DB::beginTransaction();
    $results = ['success' => 0, 'failed' => 0, 'details' => []];

    try {
        foreach ($request->user_ids as $userId) {
            try {
                $user = User::onlyTrashed()
                    ->where('type', User::TYPE_SUPER_ADMIN)
                    ->where('created_by', auth()->id())
                    ->find($userId);

                if (!$user) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => false,
                        'message' => 'User not found or not in trash'
                    ];
                    continue;
                }

                $user->restore();
                
                // REMOVED: Invitations don't have soft deletes, so no need to restore them
                // UserInvitation records remain as they were

                // Update metadata for each restored user
                $metadata = $user->metadata ?? [];
                $metadata['restored_at'] = now()->toISOString();
                $metadata['restored_by'] = auth()->id();
                $metadata['restored_by_name'] = auth()->user()->name;
                $metadata['restored_via'] = 'bulk_restore';
                $user->update(['metadata' => $metadata]);

                $results['success']++;
                $results['details'][] = [
                    'id' => $userId,
                    'success' => true,
                    'message' => 'Restored successfully'
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

        DB::commit();

        Log::info('Bulk restore completed', [
            'developer_id' => auth()->id(),
            'success_count' => $results['success'],
            'failed_count' => $results['failed']
        ]);

        return response()->json([
            'success' => $results['success'] > 0,
            'results' => $results,
            'message' => "Restored {$results['success']} out of " . count($request->user_ids) . " Super Admins"
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Bulk restore failed', [
            'developer_id' => auth()->id(),
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Bulk restore failed: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Bulk permanently delete multiple soft-deleted super admins
 */
public function bulkPermanentDelete(Request $request)
{
    $validator = Validator::make($request->all(), [
        'user_ids' => 'required|array',
        'user_ids.*' => 'exists:users,id',
        'confirmation' => 'required|accepted'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    DB::beginTransaction();
    $results = ['success' => 0, 'failed' => 0, 'details' => [], 'deleted_names' => []];

    try {
        foreach ($request->user_ids as $userId) {
            try {
                $user = User::onlyTrashed()
                    ->where('type', User::TYPE_SUPER_ADMIN)
                    ->where('created_by', auth()->id())
                    ->find($userId);

                if (!$user) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => false,
                        'message' => 'User not found or not in trash'
                    ];
                    continue;
                }

                // Check if user created other accounts
                $createdUsersCount = User::withTrashed()
                    ->where('created_by', $user->id)
                    ->where('id', '!=', $user->id)
                    ->count();
                
                if ($createdUsersCount > 0) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => false,
                        'message' => "Created {$createdUsersCount} user accounts - cannot delete"
                    ];
                    continue;
                }

                // Store name for logging
                $userName = $user->name;

                // FIXED: Use delete() instead of forceDelete() for invitations
                // This permanently removes the invitation records
                UserInvitation::where('user_id', $user->id)->delete();

                // Delete user photo if exists
                if ($user->photo && Storage::disk('public')->exists('users/photos/' . $user->photo)) {
                    Storage::disk('public')->delete('users/photos/' . $user->photo);
                }

                // Permanently delete the user
                $user->forceDelete();

                $results['success']++;
                $results['deleted_names'][] = $userName;
                $results['details'][] = [
                    'id' => $userId,
                    'success' => true,
                    'message' => 'Permanently deleted'
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

        DB::commit();

        Log::info('Bulk permanent delete completed', [
            'developer_id' => auth()->id(),
            'success_count' => $results['success'],
            'failed_count' => $results['failed'],
            'deleted_users' => $results['deleted_names']
        ]);

        return response()->json([
            'success' => $results['success'] > 0,
            'results' => $results,
            'message' => "Permanently deleted {$results['success']} out of " . count($request->user_ids) . " Super Admins"
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Bulk permanent delete failed', [
            'developer_id' => auth()->id(),
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Bulk permanent delete failed: ' . $e->getMessage()
        ], 500);
    }
}

    /**
 * Empty entire trash (permanently delete all soft-deleted super admins)
 */
public function emptyTrash(Request $request)
{
    $validator = Validator::make($request->all(), [
        'confirmation' => 'required|string|in:EMPTY_ALL_TRASH'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Please type "EMPTY_ALL_TRASH" to confirm this destructive action.'
        ], 422);
    }

    DB::beginTransaction();

    try {
        $trashedUsers = User::onlyTrashed()
            ->where('type', User::TYPE_SUPER_ADMIN)
            ->where('created_by', auth()->id())
            ->get();

        $totalCount = $trashedUsers->count();
        $deletedCount = 0;
        $failedUsers = [];

        foreach ($trashedUsers as $user) {
            try {
                // Skip if user created other accounts
                $createdUsersCount = User::withTrashed()
                    ->where('created_by', $user->id)
                    ->where('id', '!=', $user->id)
                    ->count();
                
                if ($createdUsersCount > 0) {
                    $failedUsers[] = "{$user->name} (created {$createdUsersCount} accounts)";
                    continue;
                }

                // FIXED: Use delete() instead of forceDelete() for invitations
                UserInvitation::where('user_id', $user->id)->delete();

                if ($user->photo && Storage::disk('public')->exists('users/photos/' . $user->photo)) {
                    Storage::disk('public')->delete('users/photos/' . $user->photo);
                }

                $user->forceDelete();
                $deletedCount++;

            } catch (\Exception $e) {
                $failedUsers[] = "{$user->name}: " . $e->getMessage();
                Log::error("Failed to delete user {$user->id} during empty trash", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        DB::commit();

        Log::info('Developer emptied Super Admin trash', [
            'developer_id' => auth()->id(),
            'total_deleted' => $deletedCount,
            'total_failed' => count($failedUsers)
        ]);

        $message = "Trash emptied! Permanently deleted {$deletedCount} out of {$totalCount} Super Admins.";
        
        if (!empty($failedUsers)) {
            $message .= " Failed to delete: " . implode(', ', array_slice($failedUsers, 0, 5));
            if (count($failedUsers) > 5) {
                $message .= " and " . (count($failedUsers) - 5) . " more.";
            }
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'deleted_count' => $deletedCount,
            'total_count' => $totalCount,
            'failed_users' => $failedUsers
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to empty trash', [
            'developer_id' => auth()->id(),
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to empty trash: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Show form for creating a new super admin
     */
    public function create()
    {
        $statuses = [
            User::STATUS_PENDING => 'Pending',
            User::STATUS_ACTIVE => 'Active',
        ];

        $emailConfigStatus = $this->getEmailConfigurationStatus();
        $defaultExpiryDays = config('auth.invitation_expiry_days', 7);

        $userConstants = [
            'STATUS_PENDING' => User::STATUS_PENDING,
            'STATUS_ACTIVE' => User::STATUS_ACTIVE,
            'STATUS_SUSPENDED' => User::STATUS_SUSPENDED,
            'STATUS_INACTIVE' => User::STATUS_INACTIVE,
            'TYPE_SUPER_ADMIN' => User::TYPE_SUPER_ADMIN,
        ];

        $defaultStatus = User::STATUS_PENDING;

        return view('developer.super-admins.create', compact(
            'statuses',
            'emailConfigStatus',
            'defaultExpiryDays',
            'userConstants',
            'defaultStatus'
        ));
    }

   /**
 * Store a newly created super admin with email invitation
 */
public function store(Request $request)
{
    $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'phone' => 'required|string|max:20|unique:users,phone',
        'status' => 'required|string|in:' . implode(',', [
            User::STATUS_PENDING,
            User::STATUS_ACTIVE
        ]),
        'gender' => 'nullable|string|in:male,female,other',
        'digital_address' => 'nullable|string|max:255',
        'region' => 'nullable|string|max:100',
        'location' => 'nullable|string|max:255',
        'username' => 'nullable|string|max:100|unique:users,username',
        'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        'send_invitation' => 'required|boolean',
        'custom_message' => 'nullable|string|max:1000',
        'expires_in_days' => 'nullable|integer|min:1|max:30',
        'auto_verify_phone' => 'sometimes|boolean',
        'auto_verify_email' => 'sometimes|boolean',
    ];

    // Add password validation only if NOT sending invitation
    if (!$request->boolean('send_invitation')) {
        $rules['password'] = 'required|confirmed|min:8';
        $rules['password_confirmation'] = 'required';
    }

    $validator = Validator::make($request->all(), $rules, [
        'email.unique' => 'This email is already registered.',
        'phone.unique' => 'This phone number is already registered.',
        'send_invitation.required' => 'Please specify if invitation should be sent.',
        'password.required' => 'Password is required when not sending invitation.',
        'password.confirmed' => 'Password confirmation does not match.',
    ]);

    $validator->after(function ($validator) use ($request) {
        if ($request->boolean('send_invitation')) {
            $emailStatus = $this->getEmailConfigurationStatus();
            
            if (!$emailStatus['can_send']) {
                $validator->errors()->add('send_invitation', 
                    'Email configuration is not set up or not working. Please configure email settings first.');
            }
        }
    });

    if ($validator->fails()) {
        return redirect()->back()
            ->withErrors($validator)
            ->withInput()
            ->with('error', 'Please correct the errors below.');
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
                'created_via' => 'developer_portal',
                'created_by_developer' => auth()->id(),
                'developer_name' => auth()->user()->name,
                'initial_status' => $request->status,
                'setup_method' => $request->boolean('send_invitation') ? 'invitation' : 'manual_password',
                'auto_verification_enabled' => [
                    'phone' => $request->boolean('auto_verify_phone'),
                    'email' => $request->boolean('auto_verify_email')
                ]
            ]
        ];

        // Handle password logic
        if ($request->boolean('send_invitation')) {
            $temporaryPassword = Str::random(32);
            $userData['password'] = Hash::make($temporaryPassword);
            $userData['status'] = User::STATUS_PENDING;
            $invitationPasswordHash = Hash::make($temporaryPassword);
        } else {
            $userData['password'] = Hash::make($request->password);
            
            if ($request->boolean('auto_verify_email')) {
                $userData['email_verified_at'] = now();
            }
            $userData['status'] = User::STATUS_ACTIVE;
        }

        // Handle phone verification
        if ($request->boolean('auto_verify_phone')) {
            $userData['phone_verified_at'] = now();
        }

        // Handle photo upload
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            $photoPath = $this->photoService->upload($request->file('photo'), 'super_admins');
            $userData['photo'] = $photoPath;
        }

        // Create super admin
        $user = User::create($userData);

        // 🔥 FIXED: Ensure super-admin role exists using firstOrCreate
        $superAdminRole = \App\Models\Role::firstOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Administrator',
                'display_name' => 'Super Administrator',
                'description' => 'Full system access with all permissions',
                'priority' => 1,
                'is_default' => false,
                'is_system' => true,
                'permissions' => ['*'],
                'metadata' => [
                    'created_via' => 'auto_creation',
                    'created_at' => now()->toISOString(),
                    'created_by' => auth()->id()
                ]
            ]
        );
        
        // Check if role assignment already exists to avoid duplicates
        if (!$user->roles()->where('role_id', $superAdminRole->id)->exists()) {
            // Assign the role using the pivot table with metadata
            $user->roles()->attach($superAdminRole->id, [
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
                'is_active' => true,
                'is_primary' => true,
                'metadata' => json_encode([
                    'source' => 'developer_creation',
                    'assigned_via' => 'developer_portal',
                    'developer_id' => auth()->id(),
                    'developer_name' => auth()->user()->name
                ])
            ]);
            
            \Log::info('Super admin role assigned to user', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'role_id' => $superAdminRole->id,
                'role_slug' => $superAdminRole->slug,
                'role_name' => $superAdminRole->name,
                'assigned_by' => auth()->id(),
                'assigned_by_name' => auth()->user()->name
            ]);
        } else {
            \Log::info('Super admin role already assigned to user', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'role_id' => $superAdminRole->id
            ]);
        }

        // Send invitation if requested
        $invitationResult = null;
        $invitationRecord = null;
        
        if ($request->boolean('send_invitation')) {
            $invitationResult = $this->sendSuperAdminInvitation($user, $request, $invitationPasswordHash ?? null);
            
            if (isset($invitationResult['invitation_id'])) {
                $invitationRecord = UserInvitation::find($invitationResult['invitation_id']);
            }

            if ($invitationResult['success']) {
                $user->update(['last_invitation_sent_at' => now()]);
            }
        }

        DB::commit();

        $successMessage = 'Super Admin created successfully!';
        if ($invitationResult) {
            if ($invitationResult['success']) {
                $successMessage .= " Invitation email sent successfully. User must complete account setup.";
            } else {
                $successMessage .= ' But failed to send invitation: ' . ($invitationResult['message'] ?? 'Unknown error');
            }
        }

        Log::info('Developer created Super Admin', [
            'developer_id' => auth()->id(),
            'developer_name' => auth()->user()->name,
            'super_admin_id' => $user->id,
            'super_admin_name' => $user->name,
            'super_admin_email' => $user->email,
            'setup_method' => $userData['metadata']['setup_method'],
            'status' => $user->status,
            'invitation_sent' => $request->boolean('send_invitation'),
            'role_assigned' => true,
            'role_id' => $superAdminRole->id,
            'role_name' => $superAdminRole->name
        ]);

        return redirect()->route('developer.super-admins.show', $user->id)
            ->with('success', $successMessage)
            ->with('invitation_result', $invitationResult)
            ->with('invitation_record', $invitationRecord);

    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Failed to create Super Admin', [
            'developer_id' => auth()->id(),
            'developer_name' => auth()->user()->name ?? 'Unknown',
            'error' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'error_trace' => $e->getTraceAsString(),
            'request_data' => $request->except(['password', 'password_confirmation'])
        ]);

        return back()->with('error', 'Failed to create Super Admin: ' . $e->getMessage())
            ->withInput();
    }
}

    /**
 * Display the specified super admin
 */
public function show($id)
{
    $user = User::with([
        'creator',
        'invitations' => function($query) {
            $query->orderBy('created_at', 'desc');
        },
        'roles'  // ← ADD THIS to load roles
    ])->withTrashed()->findOrFail($id);

    // Ensure only the creator developer can view
    if ($user->created_by !== auth()->id() && !auth()->user()->isSuperAdmin()) {
        abort(403, 'Unauthorized.');
    }

    // Ensure it's a super admin
    if ($user->type !== User::TYPE_SUPER_ADMIN) {
        abort(404, 'User not found.');
    }

    $userData = $this->prepareUserForDisplay($user);
    $emailConfigStatus = $this->getEmailConfigurationStatus();
    $invitationStats = $this->getInvitationStats($user);
    
    // ADD: Get available roles for Super Admin (except super-admin role itself)
    $availableRoles = \App\Models\Role::where('slug', '!=', 'super-admin')
        ->where('slug', '!=', 'developer')
        ->orderBy('priority')
        ->get();
    
    // ADD: Check if Super Admin already has landlord role
    $hasLandlordRole = $user->hasRole('landlord');
    $hasAdminRole = $user->hasRole('admin');
    $propertyCount = $user->properties()->count();
    $isPropertyOwner = $propertyCount > 0;
    
    $recentActivities = [];
    if (class_exists(\App\Models\UserActivity::class)) {
        $recentActivities = $this->getRecentActivities($user);
    }

    return view('developer.super-admins.show', compact(
        'user',
        'userData',
        'emailConfigStatus',
        'invitationStats',
        'recentActivities',
        'availableRoles',      // ADD
        'hasLandlordRole',     // ADD
        'hasAdminRole',        // ADD
        'propertyCount',       // ADD
        'isPropertyOwner'      // ADD
    ));
}

    /**
     * Show the form for editing the specified super admin
     */
    public function edit($id)
    {
        $user = User::findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            abort(403, 'Unauthorized. You can only edit Super Admins you created.');
        }

        $statuses = [
            User::STATUS_PENDING => 'Pending',
            User::STATUS_ACTIVE => 'Active',
            User::STATUS_SUSPENDED => 'Suspended',
            User::STATUS_INACTIVE => 'Inactive',
        ];

        $genders = [
            'male' => 'Male',
            'female' => 'Female',
            'other' => 'Other'
        ];

        $userData = $this->prepareUserForDisplay($user);
        $emailConfigStatus = $this->getEmailConfigurationStatus();
        
        $userConstants = [
            'STATUS_PENDING' => User::STATUS_PENDING,
            'STATUS_ACTIVE' => User::STATUS_ACTIVE,
            'STATUS_SUSPENDED' => User::STATUS_SUSPENDED,
            'STATUS_INACTIVE' => User::STATUS_INACTIVE,
            'TYPE_SUPER_ADMIN' => User::TYPE_SUPER_ADMIN,
        ];

        return view('developer.super-admins.edit', compact(
            'user',
            'userData',
            'statuses',
            'genders',
            'emailConfigStatus',
            'userConstants'
        ));
    }

   /**
 * Update the specified super admin
 */
public function update(Request $request, $id)
{
    $user = User::findOrFail($id);

    if ($user->created_by !== auth()->id()) {
        abort(403, 'Unauthorized. You can only update Super Admins you created.');
    }

    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email,' . $id,
        'phone' => 'required|string|max:20|unique:users,phone,' . $id,
        'status' => 'required|string|in:' . implode(',', [
            User::STATUS_PENDING,
            User::STATUS_ACTIVE,
            User::STATUS_SUSPENDED,
            User::STATUS_INACTIVE
        ]),
        'gender' => 'nullable|string|in:male,female,other',
        'digital_address' => 'nullable|string|max:255',
        'region' => 'nullable|string|max:100',
        'location' => 'nullable|string|max:255',
        'username' => 'nullable|string|max:100|unique:users,username,' . $id,
        'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        'remove_photo' => 'sometimes|boolean',
        'auto_verify_phone' => 'sometimes|boolean',
        'remove_phone_verification' => 'sometimes|boolean',
    ]);

    if ($validator->fails()) {
        return back()->withErrors($validator)->withInput();
    }

    DB::beginTransaction();

    try {
        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'status' => $request->status,
            'gender' => $request->gender ?? null,
            'digital_address' => $request->digital_address ?? null,
            'region' => $request->region ?? null,
            'location' => $request->location ?? null,
            'username' => $request->username ?? null,
        ];

        // 🔥 Ensure type remains SUPER_ADMIN
        $updateData['type'] = User::TYPE_SUPER_ADMIN;

        // Handle photo
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            if ($user->photo) {
                $this->photoService->delete($user->photo, 'super_admins');
            }
            $photoPath = $this->photoService->upload($request->file('photo'), 'super_admins');
            $updateData['photo'] = $photoPath;
        }

        if ($request->has('remove_photo') && $request->boolean('remove_photo') && $user->photo) {
            $this->photoService->delete($user->photo, 'super_admins');
            $updateData['photo'] = null;
        }

        // Handle phone verification
        if ($request->has('auto_verify_phone') && $request->boolean('auto_verify_phone')) {
            $updateData['phone_verified_at'] = now();
        } elseif ($request->has('remove_phone_verification') && $request->boolean('remove_phone_verification')) {
            $updateData['phone_verified_at'] = null;
        }

        // Update metadata
        $metadata = $user->metadata ?? [];
        $metadata['last_updated_by_developer'] = [
            'id' => auth()->id(),
            'name' => auth()->user()->name,
            'at' => now()->toISOString()
        ];
        $updateData['metadata'] = $metadata;

        $user->update($updateData);

        // 🔥 FIX: Check if super-admin role exists and assign if needed
        // Use the correct Role model namespace
        $superAdminRole = \App\Models\Role::where('slug', 'super-admin')->first();
        
        if ($superAdminRole) {
            // Check if user already has the role using the correct method
            if (!$user->roles()->where('role_id', $superAdminRole->id)->exists()) {
                // Use the pivot table directly instead of assignRole()
                $user->roles()->attach($superAdminRole->id, [
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                    'is_active' => true,
                    'is_primary' => true,
                    'metadata' => json_encode(['source' => 'developer_update'])
                ]);
                \Log::info('Super-admin role assigned during update', [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'updated_by' => auth()->id()
                ]);
            }
        } else {
            // Create the role if it doesn't exist
            \Log::warning('Super-admin role not found - creating it during update', [
                'user_id' => $user->id
            ]);
            
            $superAdminRole = \App\Models\Role::create([
                'name' => 'super-admin',
                'slug' => 'super-admin',
                'display_name' => 'Super Admin',
                'description' => 'Full system access with all permissions',
                'priority' => 100,
                'is_system' => true,
                'permissions' => ['*']
            ]);
            
            $user->roles()->attach($superAdminRole->id, [
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
                'is_active' => true,
                'is_primary' => true,
                'metadata' => json_encode(['source' => 'developer_update'])
            ]);
        }

        DB::commit();

        Log::info('Developer updated Super Admin', [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id,
            'updates' => array_keys($updateData)
        ]);

        return redirect()->route('developer.super-admins.show', $user->id)
            ->with('success', 'Super Admin updated successfully!');

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Developer failed to update Super Admin', [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return back()->with('error', 'Failed to update Super Admin: ' . $e->getMessage())
            ->withInput();
    }
}

    /**
 * Soft delete the specified super admin
 */
public function destroy($id)
{
    $user = User::findOrFail($id);

    if ($user->created_by !== auth()->id()) {
        return back()->with('error', 'Unauthorized. You can only delete Super Admins you created.');
    }

    if ($user->id === auth()->id()) {
        return back()->with('error', 'You cannot delete your own account.');
    }

    DB::beginTransaction();

    try {
        // Check for critical relations
        $createdUsersCount = User::where('created_by', $user->id)->count();
        if ($createdUsersCount > 0) {
            return back()->with('error', "Cannot delete Super Admin who has created {$createdUsersCount} user accounts. Move to trash or deactivate instead.");
        }

        // Store deletion info in metadata before soft delete (without deleted_by since no column)
        $metadata = $user->metadata ?? [];
        $metadata['deletion_info'] = [
            'deleted_at' => now()->toISOString(),
            'deleted_by' => auth()->id(),
            'deleted_by_name' => auth()->user()->name,
            'deletion_type' => 'soft_delete'
        ];
        $user->update(['metadata' => $metadata]);

        // Perform soft delete
        $user->delete();

        DB::commit();

        Log::info('Developer soft deleted Super Admin', [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id,
            'super_admin_name' => $user->name
        ]);

        return redirect()->route('developer.super-admins.index')
            ->with('success', 'Super Admin moved to trash successfully! You can restore it from the trash section.');

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Developer failed to delete Super Admin', [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id,
            'error' => $e->getMessage()
        ]);

        return back()->with('error', 'Failed to delete Super Admin: ' . $e->getMessage());
    }
}

    /**
     * Show the form for changing super admin password
     */
    public function showChangePasswordForm($id)
    {
        $user = User::findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        return view('developer.super-admins.change-password', compact('user'));
    }

    /**
     * Change super admin password
     */
    public function changePassword(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $validator = Validator::make($request->all(), [
            'new_password' => 'required|string|min:8|confirmed',
            'force_password_change' => 'sometimes|boolean',
            'notify_user' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $user->password = Hash::make($request->new_password);
            
            if ($request->has('force_password_change') && $request->boolean('force_password_change')) {
                $user->force_password_change = true;
            }
            
            $user->save();

            if ($request->has('notify_user') && $request->notify_user) {
                $this->sendPasswordChangeNotification($user);
            }

            Log::info('Developer changed Super Admin password', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id,
                'force_change_required' => $user->force_password_change ?? false
            ]);

            return redirect()->route('developer.super-admins.show', $user->id)
                ->with('success', 'Password changed successfully!');

        } catch (\Exception $e) {
            Log::error('Failed to change Super Admin password', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to change password: ' . $e->getMessage());
        }
    }

    /**
     * Send invitation to super admin via email
     */
    public function sendInvitation(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only send invitations to Super Admins you created.'
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

        if (!$this->canReceiveInvitation($user)) {
            return response()->json([
                'success' => false,
                'message' => 'User cannot receive invitation (already has active invitation or no email)'
            ], 422);
        }

        $emailStatus = $this->getEmailConfigurationStatus();
        if (!$emailStatus['can_send']) {
            return response()->json([
                'success' => false,
                'message' => 'Email configuration is not set up or not working. Please configure email settings first.'
            ], 422);
        }

        try {
            $invitationResult = $this->sendSuperAdminInvitation($user, $request);

            if ($invitationResult['success']) {
                Log::info('Developer sent invitation to Super Admin', [
                    'developer_id' => auth()->id(),
                    'super_admin_id' => $user->id,
                    'invitation_url' => $invitationResult['invitation_url']
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Invitation email sent successfully!',
                    'invitation_url' => $invitationResult['invitation_url'],
                    'invitation_id' => $invitationResult['invitation_id']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send invitation: ' . ($invitationResult['message'] ?? 'Unknown error')
                ], 422);
            }

        } catch (\Exception $e) {
            Log::error('Developer failed to send invitation', [
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
 * Resend invitation to super admin
 */
public function resendInvitation(Request $request, $id)
{
    $user = User::findOrFail($id);

    if ($user->created_by !== auth()->id()) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. You can only resend invitations to Super Admins you created.'
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

    $emailStatus = $this->getEmailConfigurationStatus();
    if (!$emailStatus['can_send']) {
        return response()->json([
            'success' => false,
            'message' => 'Email configuration is not set up or not working. Please configure email settings first.'
        ], 422);
    }

    DB::beginTransaction();

    try {
        // FIX: Delete existing pending invitations instead of trying to update with missing columns
        UserInvitation::where('user_id', $user->id)
            ->whereIn('status', [UserInvitation::STATUS_PENDING, UserInvitation::STATUS_SENT])
            ->where('expires_at', '>', now())
            ->delete();

        $result = $this->sendSuperAdminInvitation($user, $request);

        DB::commit();

        if ($result['success']) {
            Log::info('Developer resent invitation to Super Admin', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invitation resent successfully!',
                'invitation_url' => $result['invitation_url'] ?? null,
                'invitation_id' => $result['invitation_id'] ?? null
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend invitation: ' . ($result['message'] ?? 'Unknown error')
            ], 422);
        }

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Exception in resendInvitation: ' . $e->getMessage(), [
            'developer_id' => auth()->id(),
            'super_admin_id' => $user->id,
            'error_trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to resend invitation: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Send super admin invitation with email only
     * FIX: Removed temporary password from user metadata
     */
    private function sendSuperAdminInvitation(User $user, Request $request, $preHashedPassword = null)
    {
        try {
            $emailStatus = $this->getEmailConfigurationStatus();
            if (!$emailStatus['can_send']) {
                throw new \Exception('Email configuration is not set up or not working.');
            }

            $expiresInDays = (int) ($request->expires_in_days ?? 7);
            $expiresInDays = max(1, min(30, $expiresInDays));

            // Generate unique token
            $token = Str::random(60);
            
            // Generate temporary password (not stored in user metadata)
            $temporaryPassword = $preHashedPassword ? null : Str::random(32);
            $passwordHash = $preHashedPassword ?? Hash::make($temporaryPassword);

            // Create invitation record with password hash (not storing plain password)
            $invitation = UserInvitation::create([
                'user_id' => $user->id,
                'invited_by' => auth()->id(),
                'custom_message' => $request->custom_message ?? null,
                'expires_at' => now()->addDays($expiresInDays),
                'token' => $token,
                'status' => UserInvitation::STATUS_PENDING,
                'metadata' => [
                    'temporary_password_hash' => $passwordHash, // Store hash, not plain password
                    'expires_in_days' => $expiresInDays,
                    'created_via' => 'developer_portal'
                ]
            ]);

            // Send invitation email
            $emailResult = $this->sendInvitationEmail($user, $invitation, $request);

            if ($emailResult['success']) {
                $invitation->update([
                    'status' => UserInvitation::STATUS_SENT,
                    'sent_at' => now(),
                    'send_attempts' => ($invitation->send_attempts ?? 0) + 1
                ]);
                
                // Update user metadata with invitation info (no password)
                $metadata = $user->metadata ?? [];
                $invitationHistory = $metadata['invitation_history'] ?? [];
                $invitationHistory[] = [
                    'invitation_id' => $invitation->id,
                    'sent_at' => now()->toISOString(),
                    'channel' => 'email',
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'expires_in_days' => $expiresInDays
                ];
                $metadata['invitation_history'] = $invitationHistory;
                $user->update(['metadata' => $metadata]);
                
                Log::info("Super Admin invitation email sent successfully", [
                    'user_id' => $user->id,
                    'invitation_id' => $invitation->id,
                    'expires_in_days' => $expiresInDays
                ]);
            } else {
                $failureReason = substr($emailResult['message'], 0, 255);
                
                $invitation->update([
                    'status' => UserInvitation::STATUS_FAILED,
                    'failed_at' => now(),
                    'failure_reason' => 'Email sending failed: ' . $failureReason,
                    'send_attempts' => ($invitation->send_attempts ?? 0) + 1
                ]);
                
                Log::error("Super Admin invitation email failed", [
                    'user_id' => $user->id,
                    'invitation_id' => $invitation->id,
                    'error' => $emailResult['message']
                ]);
            }

            return [
                'success' => $emailResult['success'],
                'message' => $emailResult['success'] ? 
                    "Invitation email sent successfully" : 
                    "Failed to send invitation email: " . $emailResult['message'],
                'invitation_id' => $invitation->id,
                'token' => $invitation->token,
                'invitation_url' => route('invitation.accept', ['token' => $invitation->token]),
                'expires_in_days' => $expiresInDays
            ];

        } catch (\Exception $e) {
            $errorMessage = substr($e->getMessage(), 0, 255);
            
            Log::error('Failed to send super admin invitation: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send super admin invitation: ' . $errorMessage
            ];
        }
    }

    /**
     * Send invitation email using Laravel Mail
     */
    private function sendInvitationEmail(User $user, UserInvitation $invitation, Request $request): array
    {
        try {
            $appName = config('app.name', 'Ohwimase Stool Land');
            $invitationUrl = route('invitation.accept', ['token' => $invitation->token]);
            $expiryDate = $invitation->expires_at->format('F j, Y \a\t g:i A');
            $developerName = auth()->user()->name ?? 'System Developer';

            // Use a dedicated mailable class if available, otherwise send raw
            if (class_exists(\App\Mail\SuperAdminInvitationMail::class)) {
                Mail::to($user->email)->send(new \App\Mail\SuperAdminInvitationMail(
                    $user,
                    $invitation,
                    $appName,
                    $developerName,
                    $expiryDate,
                    $invitationUrl
                ));
            } else {
                // Fallback to raw email sending
                $emailContent = $this->generateSuperAdminInvitationEmail(
                    $user, $invitation, $appName, $developerName, $expiryDate, $invitationUrl
                );
                
                Mail::to($user->email)->send(new \Illuminate\Mail\Message($emailContent));
            }

            Log::info('Invitation email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'invitation_id' => $invitation->id
            ]);

            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];

        } catch (\Exception $e) {
            $errorMessage = substr($e->getMessage(), 0, 255);
            
            Log::error('Failed to send invitation email: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'invitation_id' => $invitation->id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $errorMessage
            ];
        }
    }

    /**
     * Generate super admin invitation email HTML
     */
    private function generateSuperAdminInvitationEmail(
        User $user, 
        UserInvitation $invitation, 
        string $appName, 
        string $developerName, 
        string $expiryDate, 
        string $invitationUrl
    ): string {
        $currentDate = now()->format('F j, Y');
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Super Admin Invitation - {$appName}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 300; }
        .content { padding: 40px; }
        .invitation-card { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4a90e2; }
        .button { background: #4a6ee0; color: white; padding: 14px 28px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; font-size: 16px; margin: 20px 0; text-align: center; }
        .button:hover { background: #3a5ec0; }
        .footer { background: #2c3e50; color: #ecf0f1; padding: 20px; text-align: center; font-size: 12px; }
        .info-item { margin-bottom: 10px; display: flex; }
        .info-label { font-weight: bold; min-width: 150px; color: #555; }
        .info-value { flex: 1; }
        .security-note { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; color: #856404; }
        .expiry-warning { color: #d32f2f; font-weight: bold; }
        .process-steps { background: #e8f4fd; border: 1px solid #b6e0fe; padding: 20px; border-radius: 8px; margin: 25px 0; }
        .step { margin: 15px 0; padding-left: 30px; position: relative; }
        .step-number { position: absolute; left: 0; top: 0; background: #4a6ee0; color: white; width: 24px; height: 24px; border-radius: 50%; text-align: center; line-height: 24px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Super Administrator Appointment</h1>
            <p>{$appName}</p>
        </div>
        <div class="content">
            <h2>Hello {$user->name},</h2>
            <p>You have been appointed as a <strong>Super Administrator</strong> for <strong>{$appName}</strong> by <strong>{$developerName}</strong>.</p>
            
            <div class="invitation-card">
                <h3>📋 Appointment Details:</h3>
                <div class="info-item">
                    <span class="info-label">Appointed By:</span>
                    <span class="info-value">{$developerName} (Developer)</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Appointment Date:</span>
                    <span class="info-value">{$currentDate}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Invitation Expires:</span>
                    <span class="info-value expiry-warning">{$expiryDate}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Your Email:</span>
                    <span class="info-value">{$user->email}</span>
                </div>
            </div>
            
            <div class="process-steps">
                <h3>🚀 Account Setup Process:</h3>
                <div class="step">
                    <div class="step-number">1</div>
                    <strong>Click the invitation link below</strong>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <strong>Set your secure password</strong> (you'll be prompted on the next screen)
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <strong>Complete your account setup</strong> and access your dashboard
                </div>
            </div>
            
            <div class="security-note">
                <p><strong>🔐 Important Security Information:</strong></p>
                <p>This is a privileged account with full system access. You will have:</p>
                <ul>
                    <li>Complete system configuration privileges</li>
                    <li>User management and permission control</li>
                    <li>System monitoring and reporting access</li>
                    <li>Developer tools and settings access</li>
                </ul>
                <p>Please complete your account setup immediately and keep your credentials secure.</p>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{$invitationUrl}" class="button" style="text-decoration: none;">
                    <strong>Click to Start Account Setup</strong>
                </a>
                <p style="font-size: 14px; color: #666; margin-top: 10px;">
                    (You will be prompted to set your password on the next screen)
                </p>
            </div>
            
            <p>If the button doesn't work, copy and paste this link into your browser:</p>
            <p style="background: #f0f0f0; padding: 10px; border-radius: 5px; word-break: break-all; font-family: monospace;">
                {$invitationUrl}
            </p>
            
            <p style="color: #666; font-size: 12px; margin-top: 30px;">
                <strong>Note:</strong> This invitation link will expire on {$expiryDate}. 
                If you need assistance, contact the system administrator.
            </p>
        </div>
        
        <div class="footer">
            <p><strong>System Security Reminder:</strong></p>
            <p>• Always use strong, unique passwords<br>
               • Enable two-factor authentication when available<br>
               • Never share your login credentials<br>
               • Regularly review account activity</p>
            <p>If you did not expect this appointment or have concerns, contact the administrator immediately.</p>
            <p>&copy; {$currentDate} {$appName}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get email configuration status
     */
    private function getEmailConfigurationStatus()
    {
        try {
            return $this->developerEmailService->checkConfigurationStatus();
        } catch (\Exception $e) {
            Log::error('Failed to get email configuration status: ' . $e->getMessage());
            return $this->getDirectEmailConfigStatus();
        }
    }

    /**
     * Get direct email configuration status (fallback)
     */
    private function getDirectEmailConfigStatus()
    {
        try {
            $config = $this->emailConfigService->getCurrentDeveloperConfig();
            
            $isConfigured = !empty($config['DEVELOPER_MAIL_HOST']) && 
                           !empty($config['DEVELOPER_MAIL_USERNAME']) && 
                           !empty($config['DEVELOPER_MAIL_PASSWORD']);
            
            return [
                'configured' => $isConfigured,
                'connection' => false,
                'authentication' => !empty($config['DEVELOPER_MAIL_USERNAME']) && !empty($config['DEVELOPER_MAIL_PASSWORD']),
                'can_send' => $isConfigured,
                'message' => $isConfigured ? 
                    'Email appears to be configured (fallback check)' : 
                    'Email configuration incomplete.',
                'last_test' => null,
                'config_source' => 'fallback',
            ];
            
        } catch (\Exception $e) {
            Log::error('Direct email config check failed: ' . $e->getMessage());
            return [
                'configured' => false,
                'connection' => false,
                'authentication' => false,
                'can_send' => false,
                'message' => 'Email configuration check failed: ' . $e->getMessage(),
                'last_test' => null
            ];
        }
    }

    /**
     * Get invitation status
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
            'has_active_invitation' => $hasActiveInvitation,
            'can_receive_invitation' => !$hasActiveInvitation && !empty($user->email),
            'email_configuration_ready' => $emailStatus['can_send'],
            'email_status' => $emailStatus,
            'latest_invitation' => $latestInvitation ? [
                'id' => $latestInvitation->id,
                'sent_at' => $latestInvitation->sent_at?->toISOString(),
                'expires_at' => $latestInvitation->expires_at?->toISOString(),
                'status' => $latestInvitation->status,
                'invitation_url' => route('invitation.accept', ['token' => $latestInvitation->token]),
                'expired' => $latestInvitation->expires_at < now(),
                'expires_in_hours' => $latestInvitation->expires_at ? $latestInvitation->expires_at->diffInHours(now()) : 0,
            ] : null,
        ]);
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
     * Get invitation details API endpoint
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

            $createdAt = $invitation->created_at;
            $expiresAt = $invitation->expires_at;
            $sentAt = $invitation->sent_at;
            
            $expiresIn = '';
            if ($expiresAt) {
                if ($expiresAt->isPast()) {
                    $expiresIn = 'Expired ' . $expiresAt->diffForHumans();
                } else {
                    $expiresIn = 'Expires in ' . $expiresAt->diffForHumans();
                }
            }

            $invitationData = [
                'id' => $invitation->id,
                'status' => $invitation->status,
                'status_color' => $statusColors[$invitation->status] ?? 'secondary',
                'status_label' => $statusLabels[$invitation->status] ?? ucfirst($invitation->status),
                'created_at' => $createdAt ? $createdAt->toISOString() : null,
                'created_at_formatted' => $createdAt ? $createdAt->format('F j, Y g:i A') : 'N/A',
                'expires_at' => $expiresAt ? $expiresAt->toISOString() : null,
                'expires_at_formatted' => $expiresAt ? $expiresAt->format('F j, Y g:i A') : 'N/A',
                'expires_in' => $expiresIn,
                'sent_at' => $sentAt ? $sentAt->toISOString() : null,
                'sent_at_formatted' => $sentAt ? $sentAt->format('F j, Y g:i A') : 'Not sent yet',
                'token' => $invitation->token,
                'invitation_url' => $invitation->token ? route('invitation.accept', ['token' => $invitation->token]) : null,
                'custom_message' => $invitation->custom_message,
                'failure_reason' => $invitation->failure_reason,
                'send_attempts' => $invitation->send_attempts ?? 0,
                'channels' => ['email'],
                'invited_by' => $invitation->invitedBy ? [
                    'id' => $invitation->invitedBy->id,
                    'name' => $invitation->invitedBy->name,
                    'email' => $invitation->invitedBy->email,
                ] : null,
                'user' => $invitation->user ? [
                    'id' => $invitation->user->id,
                    'name' => $invitation->user->name,
                    'email' => $invitation->user->email,
                    'status' => $invitation->user->status,
                ] : null,
            ];

            return response()->json([
                'success' => true,
                'invitation' => $invitationData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get invitation details: ' . $e->getMessage(), [
                'invitation_id' => $invitationId,
                'developer_id' => auth()->id(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load invitation details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
 * Prepare user for display
 */
private function prepareUserForDisplay(User $user): User
{
    $user->display_status = $this->getStatusDisplayInfo($user->status);
    $user->display_phone = $this->getPhoneDisplayInfo($user->phone, $user->phone_verified_at);
    $user->has_photo = !empty($user->photo);
    $user->initials = $this->getUserInitials($user->name, $user->email);
    $user->local_phone = $this->getLocalPhone($user->phone);
    $user->creator_name = $user->creator ? $user->creator->name : 'System';
    // REMOVED deletedBy reference
    $user->deleted_by_name = 'System'; // Add default value
    $user->age = $user->created_at ? $user->created_at->diffForHumans() : 'N/A';
    $user->last_active = $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never';
    $user->is_trashed = $user->trashed();
    
    $user->metadata_info = $this->formatMetadata($user->metadata);
    
    return $user;
}

    /**
 * Format metadata for display
 */
private function formatMetadata($metadata): array
{
    if (!$metadata) {
        return [];
    }
    
    $formatted = [];
    
    if (isset($metadata['invitation_history'])) {
        $formatted['invitation_history'] = array_map(function($invitation) {
            return [
                'sent_at' => Carbon::parse($invitation['sent_at'])->format('Y-m-d H:i'),
                'channel' => $invitation['channel'] ?? 'email',
                'expires_at' => Carbon::parse($invitation['expires_at'])->format('Y-m-d H:i'),
            ];
        }, $metadata['invitation_history']);
    }
    
    if (isset($metadata['creation'])) {
        $formatted['creation'] = [
            'via' => $metadata['created_via'] ?? 'unknown',
            'by_developer' => $metadata['created_by_developer'] ?? null,
            'developer_name' => $metadata['developer_name'] ?? null,
            'initial_status' => $metadata['initial_status'] ?? null,
        ];
    }
    
    if (isset($metadata['deletion_info'])) {
        $formatted['deletion_info'] = $metadata['deletion_info'];
        // REMOVED deletedBy reference from metadata formatting
        if (isset($formatted['deletion_info']['deleted_by_name'])) {
            // Keep the name if it exists in metadata
        } else {
            $formatted['deletion_info']['deleted_by_name'] = 'System';
        }
    }
    
    if (isset($metadata['restored_at'])) {
        $formatted['restored_at'] = Carbon::parse($metadata['restored_at'])->format('Y-m-d H:i');
        $formatted['restored_by'] = $metadata['restored_by_name'] ?? 'Unknown';
    }
    
    return $formatted;
}

    /**
     * Get status display info
     */
    private function getStatusDisplayInfo(string $status): array
    {
        $statusLabels = [
            User::STATUS_PENDING => 'Pending',
            User::STATUS_ACTIVE => 'Active',
            User::STATUS_SUSPENDED => 'Suspended',
            User::STATUS_INACTIVE => 'Inactive',
        ];

        $statusClasses = [
            User::STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            User::STATUS_ACTIVE => 'bg-green-100 text-green-800',
            User::STATUS_SUSPENDED => 'bg-red-100 text-red-800',
            User::STATUS_INACTIVE => 'bg-gray-100 text-gray-800',
        ];

        return [
            'label' => $statusLabels[$status] ?? 'Unknown',
            'class' => $statusClasses[$status] ?? 'bg-gray-100 text-gray-800',
        ];
    }

    /**
     * Get phone display info
     */
    private function getPhoneDisplayInfo(?string $phone, ?string $verifiedAt): array
    {
        $localPhone = $this->getLocalPhone($phone);

        return [
            'original' => $phone,
            'local_format' => $localPhone,
            'is_verified' => !is_null($verifiedAt),
            'verified_at' => $verifiedAt ? Carbon::parse($verifiedAt)->format('Y-m-d H:i') : null,
            'status' => !is_null($verifiedAt) ? 'Verified' : 'Not Verified',
            'status_class' => !is_null($verifiedAt) ? 'text-green-600' : 'text-red-600',
        ];
    }

    /**
     * Get local phone format
     */
    private function getLocalPhone(?string $phone): string
    {
        if (!$phone) {
            return 'Not set';
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
     * Check user relations before deletion
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
            $criticalRelations[] = "{$createdUsersCount} user accounts they created";
        }

        $activeInvitations = UserInvitation::where('user_id', $user->id)
            ->whereIn('status', ['sent', 'pending'])
            ->where('expires_at', '>', now())
            ->count();
        if ($activeInvitations > 0) {
            $criticalRelations[] = "{$activeInvitations} active invitations";
        }

        return response()->json([
            'success' => true,
            'has_critical_relations' => !empty($criticalRelations),
            'critical_relations' => $criticalRelations,
            'message' => empty($criticalRelations) 
                ? 'User can be safely deleted' 
                : 'User has critical relations that may prevent deletion',
            'can_deactivate' => true,
            'is_trashed' => $user->trashed(),
        ]);
    }

    /**
     * Activate super admin
     */
    public function activate($id)
    {
        $user = User::findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            return back()->with('error', 'Unauthorized.');
        }

        if ($user->update(['status' => User::STATUS_ACTIVE])) {
            Log::info('Developer activated Super Admin', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id
            ]);

            return back()->with('success', 'Super Admin activated successfully!');
        }

        return back()->with('error', 'Failed to activate Super Admin.');
    }

    /**
     * Suspend super admin
     */
    public function suspend($id)
    {
        $user = User::findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            return back()->with('error', 'Unauthorized.');
        }

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        if ($user->update(['status' => User::STATUS_SUSPENDED])) {
            Log::info('Developer suspended Super Admin', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id
            ]);

            return back()->with('success', 'Super Admin suspended successfully!');
        }

        return back()->with('error', 'Failed to suspend Super Admin.');
    }

    /**
     * Deactivate super admin
     */
    public function deactivate($id)
    {
        $user = User::findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            return back()->with('error', 'Unauthorized.');
        }

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($user->update(['status' => User::STATUS_INACTIVE])) {
            Log::info('Developer deactivated Super Admin', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id
            ]);

            return back()->with('success', 'Super Admin deactivated successfully!');
        }

        return back()->with('error', 'Failed to deactivate Super Admin.');
    }

    /**
     * Force verify phone
     */
    public function forceVerifyPhone($id)
    {
        $user = User::findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            return back()->with('error', 'Unauthorized.');
        }

        if ($user->update(['phone_verified_at' => now()])) {
            Log::info('Developer force verified Super Admin phone', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id
            ]);

            return back()->with('success', 'Phone verification forced successfully!');
        }

        return back()->with('error', 'Failed to force verify phone.');
    }

    /**
     * Remove phone verification
     */
    public function removePhoneVerification($id)
    {
        $user = User::findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            return back()->with('error', 'Unauthorized.');
        }

        if ($user->update(['phone_verified_at' => null])) {
            Log::info('Developer removed Super Admin phone verification', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id
            ]);

            return back()->with('success', 'Phone verification removed successfully!');
        }

        return back()->with('error', 'Failed to remove phone verification.');
    }

    /**
     * Force verify email
     */
    public function forceVerifyEmail($id)
    {
        $user = User::findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            return back()->with('error', 'Unauthorized.');
        }

        if ($user->update(['email_verified_at' => now()])) {
            Log::info('Developer force verified Super Admin email', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id
            ]);

            return back()->with('success', 'Email verification forced successfully!');
        }

        return back()->with('error', 'Failed to force verify email.');
    }

    /**
     * Remove email verification
     */
    public function removeEmailVerification($id)
    {
        $user = User::findOrFail($id);

        if ($user->created_by !== auth()->id()) {
            return back()->with('error', 'Unauthorized.');
        }

        if ($user->update(['email_verified_at' => null])) {
            Log::info('Developer removed Super Admin email verification', [
                'developer_id' => auth()->id(),
                'super_admin_id' => $user->id
            ]);

            return back()->with('success', 'Email verification removed successfully!');
        }

        return back()->with('error', 'Failed to remove email verification.');
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
            'created_today' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->whereDate('created_at', today())
                ->count(),
            'created_this_week' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'created_this_month' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];
    }

    /**
     * Get invitation statistics for user
     */
    private function getInvitationStats(User $user): array
    {
        $invitations = $user->invitations()->get();
        
        return [
            'total' => $invitations->count(),
            'sent' => $invitations->where('status', 'sent')->count(),
            'failed' => $invitations->where('status', 'failed')->count(),
            'expired' => $invitations->where('status', 'expired')->count(),
            'accepted' => $invitations->where('status', 'accepted')->count(),
            'cancelled' => $invitations->where('status', 'cancelled')->count(),
            'last_sent' => $user->last_invitation_sent_at ? 
                $user->last_invitation_sent_at->format('Y-m-d H:i') : 'Never',
        ];
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities(User $user): array
    {
        if (!class_exists(\App\Models\UserActivity::class)) {
            return [];
        }
        
        $activities = \App\Models\UserActivity::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($activity) {
                return [
                    'description' => $activity->description,
                    'created_at' => $activity->created_at->format('Y-m-d H:i'),
                    'time_ago' => $activity->created_at->diffForHumans(),
                    'metadata' => $activity->metadata,
                ];
            })
            ->toArray();
            
        return $activities;
    }

    /**
     * Export super admins
     */
    private function exportSuperAdmins($superAdmins)
    {
        $filename = 'super-admins-' . date('Y-m-d-H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($superAdmins) {
            $file = fopen('php://output', 'w');
            
            fwrite($file, "\xEF\xBB\xBF");
            
            fputcsv($file, [
                'ID', 'Name', 'Email', 'Phone', 'Status', 'Username',
                'Gender', 'Digital Address', 'Region', 'Location',
                'Created At', 'Last Login', 'Email Verified', 'Phone Verified',
                'Created By Developer', 'Is Trashed', 'Deleted At'
            ]);

            foreach ($superAdmins as $user) {
                fputcsv($file, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->phone,
                    $user->display_status['label'] ?? $user->status,
                    $user->username ?? 'N/A',
                    $user->gender ?? 'N/A',
                    $user->digital_address ?? 'N/A',
                    $user->region ?? 'N/A',
                    $user->location ?? 'N/A',
                    $user->created_at->format('Y-m-d H:i:s'),
                    $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never',
                    $user->email_verified_at ? 'Yes' : 'No',
                    $user->phone_verified_at ? 'Yes' : 'No',
                    $user->creator_name ?? 'N/A',
                    $user->trashed() ? 'Yes' : 'No',
                    $user->deleted_at ? $user->deleted_at->format('Y-m-d H:i:s') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Bulk actions for super admins
     */
    public function bulkAction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:activate,suspend,deactivate,delete,send_invitation,restore,force_delete',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->action === 'send_invitation') {
            $emailStatus = $this->getEmailConfigurationStatus();
            if (!$emailStatus['can_send']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email configuration is not set up or not working.'
                ], 422);
            }
        }

        $results = ['success' => 0, 'failed' => 0, 'details' => []];

        foreach ($request->user_ids as $userId) {
            try {
                $user = User::withTrashed()->find($userId);

                if (!$user) {
                    $results['failed']++;
                    continue;
                }

                if ($user->created_by !== auth()->id()) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => false,
                        'message' => 'Unauthorized. Not your Super Admin.'
                    ];
                    continue;
                }

                if (in_array($request->action, ['suspend', 'deactivate', 'delete']) && 
                    $user->id === auth()->id()) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => false,
                        'message' => 'Cannot perform action on your own account'
                    ];
                    continue;
                }

                switch ($request->action) {
                    case 'activate':
                        if ($user->trashed()) {
                            $results['failed']++;
                            $results['details'][] = [
                                'id' => $userId,
                                'success' => false,
                                'message' => 'Cannot activate trashed user'
                            ];
                        } else {
                            $user->update(['status' => User::STATUS_ACTIVE]);
                            $results['success']++;
                        }
                        break;

                    case 'suspend':
                        if (!$user->trashed()) {
                            $user->update(['status' => User::STATUS_SUSPENDED]);
                            $results['success']++;
                        } else {
                            $results['failed']++;
                        }
                        break;

                    case 'deactivate':
                        if (!$user->trashed()) {
                            $user->update(['status' => User::STATUS_INACTIVE]);
                            $results['success']++;
                        } else {
                            $results['failed']++;
                        }
                        break;

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
                                $results['success']++;
                            } else {
                                $results['failed']++;
                                $results['details'][] = [
                                    'id' => $userId,
                                    'success' => false,
                                    'message' => "Created {$createdUsersCount} user accounts"
                                ];
                            }
                        } else {
                            $results['failed']++;
                        }
                        break;

                    case 'restore':
                        if ($user->trashed()) {
                            $user->restore();
                            $results['success']++;
                        } else {
                            $results['failed']++;
                        }
                        break;

                    case 'force_delete':
                        if ($user->trashed()) {
                            $createdUsersCount = User::withTrashed()->where('created_by', $user->id)->where('id', '!=', $user->id)->count();
                            if ($createdUsersCount === 0) {
                                UserInvitation::where('user_id', $user->id)->forceDelete();
                                if ($user->photo && Storage::disk('public')->exists('users/photos/' . $user->photo)) {
                                    Storage::disk('public')->delete('users/photos/' . $user->photo);
                                }
                                $user->forceDelete();
                                $results['success']++;
                            } else {
                                $results['failed']++;
                            }
                        } else {
                            $results['failed']++;
                        }
                        break;

                    case 'send_invitation':
                        if (!$user->trashed() && $this->canReceiveInvitation($user)) {
                            $invitationRequest = new \Illuminate\Http\Request([
                                'expires_in_days' => $request->expires_in_days ?? 7,
                            ]);
                            $invitationResult = $this->sendSuperAdminInvitation($user, $invitationRequest);
                            
                            if ($invitationResult['success']) {
                                $results['success']++;
                            } else {
                                $results['failed']++;
                                $results['details'][] = [
                                    'id' => $userId,
                                    'success' => false,
                                    'message' => $invitationResult['message']
                                ];
                            }
                        } else {
                            $results['failed']++;
                        }
                        break;
                }

            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'id' => $userId,
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                Log::error("Bulk action failed for user {$userId}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => $results['success'] > 0,
            'results' => $results,
            'message' => "Completed: {$results['success']} successful, {$results['failed']} failed"
        ]);
    }

    /**
     * Get developer's super admin dashboard statistics (API)
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
            'trashed_super_admins' => User::onlyTrashed()
                ->where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->count(),
            'pending_invitations' => UserInvitation::where('invited_by', $developerId)
                ->whereIn('status', ['sent', 'pending'])
                ->where('expires_at', '>', now())
                ->count(),
            'invitations_sent_today' => UserInvitation::where('invited_by', $developerId)
                ->whereDate('created_at', today())
                ->count(),
            'recent_super_admins' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('created_by', $developerId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'email', 'status', 'created_at']),
        ];

        return response()->json([
            'success' => true,
            'statistics' => $statistics
        ]);
    }

    /**
     * Send password change notification via email
     */
    private function sendPasswordChangeNotification(User $user): void
    {
        try {
            $config = $this->emailConfigService->getCurrentDeveloperConfig();
            
            if (empty($config['DEVELOPER_MAIL_HOST']) || empty($config['DEVELOPER_MAIL_USERNAME']) || empty($user->email)) {
                return;
            }

            $appName = config('app.name', 'Ohwimase Stool Land');
            $developerName = auth()->user()->name;
            $timestamp = now()->format('F j, Y \a\t g:i A');
            
            $emailContent = $this->generatePasswordChangeEmail($user, $appName, $developerName, $timestamp);

            $decryptedPassword = $this->emailConfigService->getDecryptedPassword();
            
            if (empty($decryptedPassword)) {
                throw new \Exception('Developer email password not available.');
            }

            $dsn = sprintf(
                'smtp://%s:%s@%s:%d',
                urlencode($config['DEVELOPER_MAIL_USERNAME']),
                urlencode($decryptedPassword),
                $config['DEVELOPER_MAIL_HOST'],
                $config['DEVELOPER_MAIL_PORT'] ?? 587
            );

            if (!empty($config['DEVELOPER_MAIL_ENCRYPTION'])) {
                if ($config['DEVELOPER_MAIL_ENCRYPTION'] === 'ssl') {
                    $dsn .= '?verify_peer=0';
                } else if ($config['DEVELOPER_MAIL_ENCRYPTION'] === 'tls') {
                    $dsn .= '?encryption=tls';
                }
            }

            $transport = Transport::fromDsn($dsn);
            $mailer = new Mailer($transport);
            
            $email = (new Email())
                ->from($config['DEVELOPER_MAIL_FROM_ADDRESS'] ?? config('mail.from.address'))
                ->to($user->email)
                ->subject("Account Security Update - {$appName}")
                ->html($emailContent);

            $mailer->send($email);

            Log::info("Password change notification sent via email", [
                'user_id' => $user->id,
                'sent_by' => auth()->id()
            ]);

        } catch (\Exception $e) {
            Log::warning("Failed to send password change notification: " . $e->getMessage(), [
                'user_id' => $user->id
            ]);
        }
    }

    /**
     * Generate password change email
     */
    private function generatePasswordChangeEmail(
        User $user, 
        string $appName, 
        string $developerName, 
        string $timestamp
    ): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Account Security Update - {$appName}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .header { background: #d32f2f; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .warning { background: #ffebee; color: #d32f2f; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #d32f2f; }
        .footer { background: #2c3e50; color: #ecf0f1; padding: 20px; text-align: center; font-size: 12px; }
        .info-item { margin-bottom: 10px; display: flex; }
        .info-label { font-weight: bold; min-width: 150px; color: #555; }
        .info-value { flex: 1; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Account Security Update</h1>
            <p>{$appName}</p>
        </div>
        <div class="content">
            <h2>Hello {$user->name},</h2>
            <p>Your account password has been updated by <strong>{$developerName}</strong>.</p>
            
            <div class="warning">
                <h3>⚠️ Security Alert</h3>
                <p><strong>Password Change Details:</strong></p>
                <div class="info-item">
                    <span class="info-label">Changed By:</span>
                    <span class="info-value">{$developerName} (Developer)</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Changed At:</span>
                    <span class="info-value">{$timestamp}</span>
                </div>
            </div>
            
            <h3>What You Should Do:</h3>
            <ol>
                <li>If you requested this change, no action is needed.</li>
                <li>If you did <strong>NOT</strong> request this change, please:</li>
                <ul>
                    <li>Contact the system administrator immediately</li>
                    <li>Check your recent account activity</li>
                    <li>Ensure your email and phone number are up to date</li>
                </ul>
            </ol>
            
            <div class="footer">
                <p><strong>Account Security Tips:</strong></p>
                <ul style="text-align: left; margin: 10px 0; padding-left: 20px;">
                    <li>Use a strong, unique password</li>
                    <li>Enable two-factor authentication if available</li>
                    <li>Never share your login credentials</li>
                    <li>Regularly review your account activity</li>
                </ul>
                <p>If you have any questions or concerns, please contact the system administrator.</p>
                <p>Thank you for helping us keep your account secure,<br>{$appName} Security Team</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get available invitation channels for user (AJAX)
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
                'icon' => 'fas fa-envelope',
                'color' => 'primary'
            ];
        } elseif (!empty($user->email)) {
            $channels['email'] = [
                'name' => 'Email',
                'available' => false,
                'address' => $user->email,
                'status' => 'email_not_configured',
                'icon' => 'fas fa-envelope',
                'color' => 'secondary'
            ];
        }

        return response()->json([
            'success' => true,
            'channels' => $channels,
            'has_email' => !empty($user->email),
            'email_status' => $emailStatus
        ]);
    }

    /**
 * Display invitation history for a super admin
 */
public function invitationHistory($id)
{
    $user = User::findOrFail($id);
    
    if ($user->created_by !== auth()->id()) {
        abort(403);
    }
    
    $invitations = $user->invitations()->orderBy('created_at', 'desc')->paginate(20);
    
    return view('developer.super-admins.invitation-history', compact('user', 'invitations'));
}

/**
 * Display activities for a super admin
 */
public function activities($id)
{
    $user = User::findOrFail($id);
    
    if ($user->created_by !== auth()->id()) {
        abort(403);
    }
    
    // Fetch activities from your activity log system
    // This depends on how you track activities
    $activities = \App\Models\UserActivity::where('user_id', $user->id)
        ->orderBy('created_at', 'desc')
        ->paginate(20);
    
    return view('developer.super-admins.activities', compact('user', 'activities'));
}

/**
 * Export activities to CSV
 */
public function exportActivities($id)
{
    $user = User::findOrFail($id);
    
    if ($user->created_by !== auth()->id()) {
        abort(403);
    }
    
    $activities = \App\Models\UserActivity::where('user_id', $user->id)
        ->orderBy('created_at', 'desc')
        ->get();
    
    $filename = "user-{$user->id}-activities-" . date('Y-m-d') . ".csv";
    
    $headers = [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => "attachment; filename={$filename}",
    ];
    
    $callback = function() use ($activities) {
        $file = fopen('php://output', 'w');
        fputcsv($file, ['Date', 'Activity', 'IP Address', 'User Agent']);
        
        foreach ($activities as $activity) {
            fputcsv($file, [
                $activity->created_at->format('Y-m-d H:i:s'),
                $activity->description,
                $activity->ip_address ?? 'N/A',
                $activity->user_agent ?? 'N/A'
            ]);
        }
        
        fclose($file);
    };
    
    return response()->stream($callback, 200, $headers);
}

}
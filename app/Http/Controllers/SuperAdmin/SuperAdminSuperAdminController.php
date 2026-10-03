<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use App\Models\UserActivity;
use App\Models\Role;
use App\Models\Property;  // ✅ ADD THIS - Needed for property count check
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;  // ✅ ADD THIS - Needed for Auth::user()
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SuperAdminSuperAdminController extends Controller
{
    /**
 * Display a listing of all super admins (read-only)
 */
public function index(Request $request)
{
    // Query all super admins (not just created by current user)
    $query = User::where('type', User::TYPE_SUPER_ADMIN)
        ->with(['creator', 'roles'])  // ✅ ADDED: Load roles for role display
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

    // Add display data and role information
    $superAdmins->getCollection()->transform(function ($user) {
        $user = $this->prepareUserForDisplay($user);
        
        // ✅ ADDED: Role information for each super admin
        $user->has_landlord_role = $user->hasRole('landlord');
        $user->has_admin_role = $user->hasRole('admin');
        $user->property_count = $user->properties()->count();
        $user->is_own_profile = auth()->id() == $user->id;
        
        return $user;
    });

    $statuses = [
        User::STATUS_PENDING => 'Pending',
        User::STATUS_ACTIVE => 'Active',
        User::STATUS_SUSPENDED => 'Suspended',
        User::STATUS_INACTIVE => 'Inactive',
    ];

    // Get statistics for all super admins
    $statistics = $this->getStatistics();
    
    // Get developer statistics (for distribution view)
    $developerStatistics = $this->getDeveloperStatistics();

    // Export functionality
    if ($request->has('export') && $request->export == 'csv') {
        return $this->exportSuperAdmins($superAdmins);
    }

    // Use correct view path for Super Admin
    return view('super-admin.super-admins.index', compact(
        'superAdmins', 
        'statuses',
        'statistics',
        'developerStatistics'
    ));
}

    /**
     * Display the specified super admin (read-only)
     */
    public function show($id)
    {
        $user = User::with([
            'creator',
            'invitations' => function($query) {
                $query->orderBy('created_at', 'desc');
            },
            'roles'  // Load roles for role management
        ])->findOrFail($id);

        // Ensure it's a super admin
        if ($user->type !== User::TYPE_SUPER_ADMIN) {
            abort(404, 'User not found.');
        }

        $userData = $this->prepareUserForDisplay($user);
        
        // Get invitation statistics
        $invitationStats = $this->getInvitationStats($user);
        
        // Get recent activities
        $recentActivities = $this->getRecentActivities($user);
        
        // ==================== ROLE MANAGEMENT DATA ====================
        // Get available roles for Super Admin (except super-admin role itself)
        $availableRoles = Role::where('slug', '!=', 'super-admin')
            ->where('slug', '!=', 'developer')
            ->orderBy('priority')
            ->get();
        
        $hasLandlordRole = $user->hasRole('landlord');
        $hasAdminRole = $user->hasRole('admin');
        $propertyCount = $user->properties()->count();
        $isPropertyOwner = $propertyCount > 0;

        // Use correct view path for Super Admin
        return view('super-admin.super-admins.show', compact(
            'user',
            'userData',
            'invitationStats',
            'recentActivities',
            'availableRoles',
            'hasLandlordRole',
            'hasAdminRole',
            'propertyCount',
            'isPropertyOwner'
        ));
    }

   /**
 * Update own roles (add/remove roles for self)
 * 
 * IMPORTANT: This should ONLY modify the roles relationship,
 * NOT change the legacy type column!
 */
public function updateOwnRoles(Request $request, $id)
{
    try {
        $user = Auth::user();
        
        // Security: Only allow updating own roles
        if ($user->id != $id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update your own roles.'
            ], 403);
        }
        
        // Only super admins can update their own roles
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only Super Admins can manage their own roles.'
            ], 403);
        }
        
        $roleAction = $request->input('role_action');
        $roleSlug = $request->input('role_slug');
        
        // Validate role slug
        $allowedRoles = ['landlord', 'admin'];
        if (!in_array($roleSlug, $allowedRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role specified. Allowed roles: landlord, admin.'
            ], 400);
        }
        
        // 🔥 FIX: Find the role using your custom Role model
        $role = \App\Models\Role::where('slug', $roleSlug)->first();
        
        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => "Role '{$roleSlug}' does not exist in the system."
            ], 404);
        }
        
        if ($roleAction === 'add') {
            // Check if already has role (using your custom relationship)
            if ($user->roles()->where('role_id', $role->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => "You already have the {$roleSlug} role."
                ], 400);
            }
            
            // Add the role using your pivot table
            $user->roles()->attach($role->id, [
                'assigned_by' => $user->id,
                'assigned_at' => now(),
                'is_active' => true,
                'is_primary' => false,
                'metadata' => json_encode(['source' => 'self_assignment'])
            ]);
            
            // Log the activity
            if (function_exists('activity')) {
                activity()
                    ->performedOn($user)
                    ->causedBy($user)
                    ->withProperties(['role' => $roleSlug, 'action' => 'add'])
                    ->log("Added {$roleSlug} role to own account");
            }
            
            return response()->json([
                'success' => true,
                'message' => ucfirst($roleSlug) . " role added successfully!",
                'has_role' => true,
                'user_type' => $user->type
            ]);
            
        } elseif ($roleAction === 'remove') {
            // Check if has role
            if (!$user->roles()->where('role_id', $role->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => "You don't have the {$roleSlug} role."
                ], 400);
            }
            
            // Special check for landlord role with properties
            if ($roleSlug === 'landlord') {
                $propertyCount = Property::where('landlord_id', $user->id)->count();
                if ($propertyCount > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => "Cannot remove Landlord role. You own {$propertyCount} property(s). Transfer ownership first."
                    ], 400);
                }
            }
            
            // Remove the role using your pivot table
            $user->roles()->detach($role->id);
            
            // Log the activity
            if (function_exists('activity')) {
                activity()
                    ->performedOn($user)
                    ->causedBy($user)
                    ->withProperties(['role' => $roleSlug, 'action' => 'remove'])
                    ->log("Removed {$roleSlug} role from own account");
            }
            
            return response()->json([
                'success' => true,
                'message' => ucfirst($roleSlug) . " role removed successfully!",
                'has_role' => false,
                'user_type' => $user->type
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Invalid action specified. Use "add" or "remove".'
        ], 400);
        
    } catch (\Exception $e) {
        \Log::error('Error updating own roles: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        
        return response()->json([
            'success' => false,
            'message' => 'An error occurred: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * View super admin activities (read-only)
     */
    public function activities($id)
    {
        $user = User::findOrFail($id);

        // Ensure it's a super admin
        if ($user->type !== User::TYPE_SUPER_ADMIN) {
            abort(404, 'User not found.');
        }

        $activities = UserActivity::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('super-admin.super-admins.activities', compact('user', 'activities'));
    }

    /**
     * Export super admin activities to CSV
     */
    public function exportActivities(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Ensure it's a super admin
        if ($user->type !== User::TYPE_SUPER_ADMIN) {
            abort(404, 'User not found.');
        }

        // Get activities with filters
        $query = UserActivity::where('user_id', $user->id);

        // Apply filters
        if ($request->has('type') && $request->type != 'all') {
            $query->where('activity_type', $request->type);
        }

        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $activities = $query->orderBy('created_at', 'desc')->get();

        // Prepare CSV
        $filename = 'super-admin-activities-' . $user->id . '-' . date('Y-m-d-H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($activities, $user) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fwrite($file, "\xEF\xBB\xBF");
            
            // Headers
            fputcsv($file, [
                'Activity ID',
                'Date & Time',
                'Activity Type',
                'Description',
                'Performed By',
                'IP Address',
                'Device',
                'Browser',
                'Level',
                'Status',
                'Metadata'
            ]);

            // Data
            foreach ($activities as $activity) {
                $deviceInfo = $activity->metadata['user_agent_parsed'] ?? [];
                
                fputcsv($file, [
                    $activity->id,
                    $activity->created_at->format('Y-m-d H:i:s'),
                    $activity->type_display,
                    $activity->description,
                    $activity->performer_name,
                    $activity->ip_address ?? 'N/A',
                    $deviceInfo['device_type'] ?? 'N/A',
                    $deviceInfo['browser'] ?? 'N/A',
                    $activity->level_display,
                    $activity->is_read ? 'Read' : 'Unread',
                    json_encode($activity->metadata, JSON_UNESCAPED_SLASHES)
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * View invitation history (read-only)
     */
    public function invitationHistory($id)
    {
        $user = User::with(['invitations' => function($query) {
            $query->orderBy('created_at', 'desc');
        }])->findOrFail($id);

        // Ensure it's a super admin
        if ($user->type !== User::TYPE_SUPER_ADMIN) {
            abort(404, 'User not found.');
        }

        $invitations = $user->invitations;
        
        // Prepare statistics
        $stats = [
            'total' => $invitations->count(),
            'sent' => $invitations->where('status', 'sent')->count(),
            'accepted' => $invitations->where('status', 'accepted')->count(),
            'expired' => $invitations->where('status', 'expired')->count(),
            'failed' => $invitations->where('status', 'failed')->count(),
            'pending' => $invitations->where('status', 'pending')->count(),
        ];

        $userData = $this->prepareUserForDisplay($user);

        return view('super-admin.super-admins.invitation-history', compact(
            'user',
            'invitations',
            'stats',
            'userData'
        ));
    }

    /**
     * Export super admins to CSV
     */
    public function exportSuperAdmins($superAdmins)
    {
        $filename = 'super-admins-' . date('Y-m-d-H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($superAdmins) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fwrite($file, "\xEF\xBB\xBF");
            
            // Headers
            fputcsv($file, [
                'ID', 'Name', 'Email', 'Phone', 'Status', 'Username',
                'Gender', 'Digital Address', 'Region', 'Location',
                'Created At', 'Last Login', 'Email Verified', 'Phone Verified',
                'Created By Developer', 'Developer Email'
            ]);

            // Data
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
                    $user->creator->name ?? 'N/A',
                    $user->creator->email ?? 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get statistics for all super admins
     */
    private function getStatistics(): array
    {
        return [
            'total' => User::where('type', User::TYPE_SUPER_ADMIN)->count(),
            'active' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->count(),
            'pending' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_PENDING)
                ->count(),
            'suspended' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_SUSPENDED)
                ->count(),
            'inactive' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_INACTIVE)
                ->count(),
            'trashed' => User::onlyTrashed()->where('type', User::TYPE_SUPER_ADMIN)->count(),
            'created_today' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->whereDate('created_at', today())
                ->count(),
            'created_this_week' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'created_this_month' => User::where('type', User::TYPE_SUPER_ADMIN)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];
    }

    /**
     * Get developer statistics for super admin distribution view
     */
    private function getDeveloperStatistics(): \Illuminate\Support\Collection
    {
        return User::where('type', User::TYPE_SUPER_ADMIN)
            ->with('creator')
            ->get()
            ->groupBy('created_by')
            ->map(function ($group, $developerId) {
                $developer = $group->first()->creator;
                
                return (object)[
                    'developer_id' => $developerId,
                    'developer_name' => $developer ? $developer->name : 'System',
                    'developer_email' => $developer ? $developer->email : 'system@example.com',
                    'total' => $group->count(),
                    'active' => $group->where('status', User::STATUS_ACTIVE)->count(),
                    'pending' => $group->where('status', User::STATUS_PENDING)->count(),
                    'suspended' => $group->where('status', User::STATUS_SUSPENDED)->count(),
                    'inactive' => $group->where('status', User::STATUS_INACTIVE)->count(),
                    'last_created_at' => $group->max('created_at'),
                ];
            })
            ->sortByDesc('total')
            ->values();
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
        if (!class_exists(UserActivity::class)) {
            return [];
        }
        
        $activities = UserActivity::where('user_id', $user->id)
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
        $user->age = $user->created_at ? $user->created_at->diffForHumans() : 'N/A';
        $user->last_active = $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never';
        
        return $user;
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
     * Get invitation status (AJAX)
     */
    public function getInvitationStatus($id)
    {
        $user = User::findOrFail($id);

        // Ensure it's a super admin
        if ($user->type !== User::TYPE_SUPER_ADMIN) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        $latestInvitation = $user->invitations()->latest()->first();
        
        return response()->json([
            'success' => true,
            'has_invitations' => $user->invitations()->count() > 0,
            'latest_invitation' => $latestInvitation ? [
                'id' => $latestInvitation->id,
                'sent_at' => $latestInvitation->sent_at?->toISOString(),
                'expires_at' => $latestInvitation->expires_at?->toISOString(),
                'status' => $latestInvitation->status,
            ] : null,
        ]);
    }

    /**
     * Get invitation details (AJAX)
     */
    public function getInvitationDetails($invitationId)
    {
        try {
            $invitation = UserInvitation::with(['user', 'invitedBy'])->findOrFail($invitationId);
            
            // Format dates
            $createdAt = $invitation->created_at;
            $expiresAt = $invitation->expires_at;
            $sentAt = $invitation->sent_at;

            $invitationData = [
                'id' => $invitation->id,
                'status' => $invitation->status,
                'status_color' => $this->getInvitationStatusColor($invitation->status),
                'status_label' => $this->getInvitationStatusLabel($invitation->status),
                'created_at' => $createdAt ? $createdAt->toISOString() : null,
                'created_at_formatted' => $createdAt ? $createdAt->format('F j, Y g:i A') : 'N/A',
                'expires_at' => $expiresAt ? $expiresAt->toISOString() : null,
                'expires_at_formatted' => $expiresAt ? $expiresAt->format('F j, Y g:i A') : 'N/A',
                'sent_at' => $sentAt ? $sentAt->toISOString() : null,
                'sent_at_formatted' => $sentAt ? $sentAt->format('F j, Y g:i A') : 'Not sent yet',
                'custom_message' => $invitation->custom_message,
                'failure_reason' => $invitation->failure_reason,
                'invited_by' => $invitation->invitedBy ? [
                    'id' => $invitation->invitedBy->id,
                    'name' => $invitation->invitedBy->name,
                    'email' => $invitation->invitedBy->email,
                ] : null,
                'user' => $invitation->user ? [
                    'id' => $invitation->user->id,
                    'name' => $invitation->user->name,
                    'email' => $invitation->user->email,
                ] : null,
            ];

            return response()->json([
                'success' => true,
                'invitation' => $invitationData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get invitation details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load invitation details.'
            ], 500);
        }
    }

    /**
     * Get invitation status color
     */
    private function getInvitationStatusColor($status): string
    {
        $colors = [
            'sent' => 'warning',
            'accepted' => 'success',
            'expired' => 'danger',
            'failed' => 'danger',
            'cancelled' => 'secondary',
            'pending' => 'warning',
        ];
        return $colors[$status] ?? 'secondary';
    }

    /**
     * Get invitation status label
     */
    private function getInvitationStatusLabel($status): string
    {
        $labels = [
            'sent' => 'Sent',
            'accepted' => 'Accepted',
            'expired' => 'Expired',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'pending' => 'Pending',
        ];
        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Dashboard statistics (AJAX)
     */
    public function dashboardStatistics()
    {
        $statistics = $this->getStatistics();
        $developerStatistics = $this->getDeveloperStatistics();

        return response()->json([
            'success' => true,
            'statistics' => $statistics,
            'developer_statistics' => $developerStatistics
        ]);
    }
}
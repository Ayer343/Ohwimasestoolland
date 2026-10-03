<?php
// app/Repositories/UserRepository.php

namespace App\Repositories;

use App\Models\User;
use App\Models\UserInvitation;
use App\Models\SanitationPersonnel;
use App\Models\SecurityPersonnel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class UserRepository
{
    // ==================== ✅ FIX: CANONICAL TYPE RESOLUTION ====================

    /**
     * Canonical slug → User::TYPE_* map.
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

    protected function resolveUserTypeFilter($raw): ?int
    {
        if ($raw === null || $raw === '' || $raw === 'all') {
            return null;
        }

        if (is_int($raw)) {
            return $this->isKnownTypeId($raw) ? $raw : null;
        }

        if (!is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '' || $trimmed === 'all') {
            return null;
        }

        if (ctype_digit($trimmed)) {
            $asInt = (int) $trimmed;
            return $this->isKnownTypeId($asInt) ? $asInt : null;
        }

        $slug = strtolower(str_replace(['-', ' '], '_', $trimmed));

        return self::TYPE_SLUG_TO_CONSTANT[$slug] ?? null;
    }

    protected function isKnownTypeId(int $id): bool
    {
        return in_array($id, array_values(self::TYPE_SLUG_TO_CONSTANT), true);
    }

    public function getPaginatedUsers(array $filters, User $currentUser, int $perPage = 20): LengthAwarePaginator
    {
        $query = User::with(['creator'])->orderBy('created_at', 'desc');

        $this->applyFilters($query, $filters);
        $this->applyAuthorizationScope($query, $currentUser);

        return $query->paginate($perPage);
    }

    public function getUserStatistics(User $user): array
    {
        $stats = [];

        switch ($user->type) {
            case User::TYPE_FIELD_AGENT:
                $stats = $this->getFieldAgentStatistics($user);
                break;
            case User::TYPE_LANDLORD:
                $stats = $this->getLandlordStatistics($user);
                break;
            case User::TYPE_TENANT:
                $stats = $this->getTenantStatistics($user);
                break;
            case User::TYPE_ADMIN:
            case User::TYPE_SUPER_ADMIN:
                $stats = $this->getAdminStatistics($user);
                break;
            case User::TYPE_SECURITY_PERSONNEL:
                $stats = $this->getSecurityPersonnelStatistics($user);
                break;
            case User::TYPE_SANITATION_PERSONNEL:
                $stats = $this->getSanitationPersonnelStatistics($user);
                break;
            case User::TYPE_CONTRACTOR:
                $stats = $this->getContractorStatistics($user);
                break;
        }

        return $stats;
    }

    public function createUser(array $data, User $creator): User
    {
        return DB::transaction(function () use ($data, $creator) {
            $userData = $this->prepareUserData($data, $creator);
            $user = User::create($userData);

            $this->handleDynamicRoleAssignment($user, $data);
            $this->handleSupervisorFields($user, $data);
            $this->handleSanitationPersonnelFields($user, $data);

            if (method_exists($user, 'ensureRoleConsistency')) {
                $user->ensureRoleConsistency();
            }

            return $user;
        });
    }

    protected function handleDynamicRoleAssignment(User $user, array $data): void
    {
        $userType = (int) $data['type'];

        $roleMapping = [
            User::TYPE_SUPER_ADMIN => 'super-admin',
            User::TYPE_ADMIN       => 'admin',
            User::TYPE_LANDLORD    => 'landlord',
            User::TYPE_TENANT      => 'tenant',
            User::TYPE_FIELD_AGENT => 'field-agent',
            User::TYPE_CONTRACTOR  => 'contractor',
        ];

        $skipSpatieRoles = in_array($userType, [
            User::TYPE_SECURITY_PERSONNEL,
            User::TYPE_SANITATION_PERSONNEL,
        ]);

        if ($skipSpatieRoles) {
            Log::info('Skipping Spatie role assignment for user type', [
                'user_id'   => $user->id,
                'user_type' => $userType,
                'type_name' => $user->getTypeName(),
            ]);
            return;
        }

        if (isset($roleMapping[$userType])) {
            $primaryRoleSlug = $roleMapping[$userType];
            if ($this->roleExists($primaryRoleSlug) && !$user->hasRole($primaryRoleSlug)) {
                $user->assignRole($primaryRoleSlug);
            }
        }

        $grantLandlordRole = !empty($data['grant_landlord_role']) || $userType === User::TYPE_LANDLORD;
        if ($grantLandlordRole && $this->roleExists('landlord') && !$user->hasRole('landlord')) {
            $user->assignRole('landlord');
        }

        $grantAdminRole = !empty($data['grant_admin_role']) && $userType !== User::TYPE_ADMIN;
        if ($grantAdminRole && $this->roleExists('admin') && !$user->hasRole('admin')) {
            $user->assignRole('admin');
        }

        Log::info('Dynamic role assignment completed', [
            'user_id'        => $user->id,
            'user_name'      => $user->name,
            'user_type'      => $userType,
            'assigned_roles' => $user->roles->pluck('slug')->toArray(),
        ]);
    }

    protected function roleExists(string $roleName): bool
    {
        try {
            if (class_exists('\Spatie\Permission\Models\Role')) {
                return \Spatie\Permission\Models\Role::where('name', $roleName)->exists();
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function handleSanitationPersonnelFields(User $user, array $data): void
    {
        $isSanitationPersonnel = $user->type === User::TYPE_SANITATION_PERSONNEL;
        $isEnabled = !empty($data['sanitation_can_be_supervisor']) || !empty($data['sanitation_enabled']);

        if (!$isSanitationPersonnel && !$isEnabled) {
            return;
        }

        try {
            $sanitationPersonnel = $user->sanitationPersonnel;

            if (!$sanitationPersonnel) {
                $sanitationPersonnel = new SanitationPersonnel();
                $sanitationPersonnel->user_id = $user->id;
                $sanitationPersonnel->employee_id = $this->generateEmployeeId('SAN');
                $sanitationPersonnel->first_name = $data['name'] ?? $user->name;
                $sanitationPersonnel->last_name = '';
                $sanitationPersonnel->phone = $data['phone'] ?? $user->phone;
                $sanitationPersonnel->email = $data['email'] ?? $user->email;
            }

            $role = $data['sanitation_personnel_role'] ?? 'worker';
            $allowedRoles = ['supervisor', 'worker', 'driver', 'collector', 'team-lead', 'manager'];
            if (!in_array($role, $allowedRoles, true)) {
                $role = 'worker';
                Log::warning('Invalid sanitation role provided, defaulting to worker', [
                    'user_id'       => $user->id,
                    'provided_role' => $data['sanitation_personnel_role'] ?? null,
                ]);
            }

            $status = $data['sanitation_personnel_status'] ?? 'active';
            $allowedStatuses = ['active', 'inactive', 'on_leave', 'suspended'];
            if (!in_array($status, $allowedStatuses, true)) {
                $status = 'active';
            }

            $supervisorId = null;
            $rawSupervisor = $data['sanitation_supervisor_id'] ?? null;

            if (is_array($rawSupervisor)) {
                $rawSupervisor = reset($rawSupervisor);
            }

            if (!empty($rawSupervisor) && is_numeric($rawSupervisor)) {
                $supervisorId = (int) $rawSupervisor;

                $supervisorExists = SanitationPersonnel::where('id', $supervisorId)
                    ->where('id', '!=', $sanitationPersonnel->id ?? 0)
                    ->exists();

                if (!$supervisorExists) {
                    Log::warning('Invalid sanitation supervisor id, ignoring', [
                        'user_id'                  => $user->id,
                        'sanitation_supervisor_id' => $supervisorId,
                    ]);
                    $supervisorId = null;
                }
            }

            $supervisorRoles = SanitationPersonnel::SUPERVISOR_ROLES;
            $canBeSupervisor = in_array($role, $supervisorRoles, true)
                || !empty($data['sanitation_can_be_supervisor']);

            $sanitationPersonnel->role = $role;
            $sanitationPersonnel->status = $status;
            $sanitationPersonnel->supervisor_id = $supervisorId;
            $sanitationPersonnel->can_be_supervisor = (bool) $canBeSupervisor;
            $sanitationPersonnel->first_name = $data['name'] ?? $sanitationPersonnel->first_name ?? $user->name;
            $sanitationPersonnel->phone = $data['phone'] ?? $sanitationPersonnel->phone ?? $user->phone;
            $sanitationPersonnel->email = $data['email'] ?? $sanitationPersonnel->email ?? $user->email;

            if (!empty($data['sanitation_vehicle_number'])) {
                $sanitationPersonnel->vehicle_number = $data['sanitation_vehicle_number'];
            }

            if (!empty($data['sanitation_vehicle_type'])) {
                $sanitationPersonnel->vehicle_type = $data['sanitation_vehicle_type'];
            }

            if (!empty($data['sanitation_emergency_contact'])) {
                $sanitationPersonnel->emergency_contact = $data['sanitation_emergency_contact'];
            }

            if (!empty($data['sanitation_hire_date'])) {
                $sanitationPersonnel->hire_date = $data['sanitation_hire_date'];
            } elseif (!$sanitationPersonnel->hire_date) {
                $sanitationPersonnel->hire_date = now();
            }

            if (!empty($data['address'])) {
                $sanitationPersonnel->address = $data['address'];
            }

            $existingMetadata = $sanitationPersonnel->metadata;

            if (is_string($existingMetadata)) {
                $metadata = json_decode($existingMetadata, true) ?: [];
            } elseif (is_array($existingMetadata)) {
                $metadata = $existingMetadata;
            } else {
                $metadata = [];
            }

            if (empty($metadata['created_by'])) {
                $metadata['created_by'] = auth()->id();
                $metadata['created_by_name'] = auth()->user()->name ?? 'System';
                $metadata['created_at'] = now()->toISOString();
            }

            $metadata['updated_by'] = auth()->id();
            $metadata['updated_by_name'] = auth()->user()->name ?? 'System';
            $metadata['updated_at'] = now()->toISOString();

            $sanitationPersonnel->metadata = json_encode($metadata);
            $sanitationPersonnel->save();

            Log::info('Sanitation personnel record saved', [
                'user_id'           => $user->id,
                'personnel_id'      => $sanitationPersonnel->id,
                'role'              => $role,
                'status'            => $status,
                'supervisor_id'     => $supervisorId,
                'can_be_supervisor' => (bool) $canBeSupervisor,
                'employee_id'       => $sanitationPersonnel->employee_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save sanitation personnel record', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    protected function generateEmployeeId(string $prefix): string
    {
        $year = date('Y');
        $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $employeeId = $prefix . $year . $random;

        if (class_exists('\App\Models\SanitationPersonnel')) {
            $exists = SanitationPersonnel::where('employee_id', $employeeId)->exists();
            if ($exists) {
                return $this->generateEmployeeId($prefix);
            }
        }

        return $employeeId;
    }

    protected function handleSupervisorFields(User $user, array $data): void
    {
        if ($user->type === User::TYPE_SECURITY_PERSONNEL) {
            $user->can_be_supervisor = !empty($data['can_be_supervisor']) || !empty($data['security_can_be_supervisor']);
            $user->supervisor_level = $data['supervisor_level'] ?? $data['security_supervisor_level'] ?? 0;
            $user->supervisor_score = $data['supervisor_score'] ?? $data['security_supervisor_score'] ?? 0;

            if (!empty($data['supervisor_certifications']) || !empty($data['security_supervisor_certifications'])) {
                $certData = $data['supervisor_certifications'] ?? $data['security_supervisor_certifications'] ?? '';
                $certifications = json_decode($certData, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($certifications)) {
                    $user->supervisor_certifications = $certifications;
                }
            }

            if ($user->supervisor_level > 0) {
                $user->can_be_supervisor = true;
            }

            $user->save();

            Log::info('Security supervisor fields saved', [
                'user_id'           => $user->id,
                'can_be_supervisor' => $user->can_be_supervisor,
                'supervisor_level'  => $user->supervisor_level,
                'supervisor_score'  => $user->supervisor_score,
            ]);
        }
    }

    public function updateUser(User $user, array $data, User $currentUser): bool
    {
        $oldType = $user->type;

        $updatableFields = [
            'name', 'email', 'phone', 'username', 'type', 'status',
            'gender', 'dob', 'region', 'digital_address', 'location',
            'supervisor_level', 'supervisor_score', 'supervisor_certifications',
            'can_be_supervisor',
        ];

        $updateData = [];

        foreach ($updatableFields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'dob' && !empty($data['dob'])) {
                    $updateData[$field] = $data['dob'];
                } elseif ($field === 'supervisor_certifications' && !empty($data[$field])) {
                    $updateData[$field] = is_string($data[$field]) ? $data[$field] : json_encode($data[$field]);
                } elseif ($field === 'can_be_supervisor') {
                    $updateData[$field] = (bool) $data[$field];
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }

        if (isset($data['auto_verify_phone']) && $data['auto_verify_phone']) {
            $updateData['phone_verified_at'] = now();
        }

        if (isset($data['remove_phone_verification']) && $data['remove_phone_verification']) {
            $updateData['phone_verified_at'] = null;
        }

        if (isset($data['auto_verify_email']) && $data['auto_verify_email']) {
            $updateData['email_verified_at'] = now();
        }

        $updated = $user->update($updateData);

        if ($updated) {
            $this->syncRolesOnTypeChange($user, $oldType, $data);
            $this->handleSanitationPersonnelFields($user, $data);

            if (method_exists($user, 'ensureRoleConsistency')) {
                $user->ensureRoleConsistency();
            }
        }

        return $updated;
    }

    protected function syncRolesOnTypeChange(User $user, int $oldType, array $data): void
    {
        $newType = (int) $data['type'];

        if ($newType === $oldType) {
            return;
        }

        $roleMapping = [
            User::TYPE_SUPER_ADMIN => 'super-admin',
            User::TYPE_ADMIN       => 'admin',
            User::TYPE_LANDLORD    => 'landlord',
            User::TYPE_TENANT      => 'tenant',
            User::TYPE_FIELD_AGENT => 'field-agent',
            User::TYPE_CONTRACTOR  => 'contractor',
        ];

        if (isset($roleMapping[$oldType])) {
            $oldRoleSlug = $roleMapping[$oldType];
            $shouldKeep = false;

            if ($oldRoleSlug === 'landlord' && method_exists($user, 'isPropertyOwner') && $user->isPropertyOwner()) {
                $shouldKeep = true;
            }

            if (!$shouldKeep && $user->hasRole($oldRoleSlug)) {
                try {
                    $user->removeRole($oldRoleSlug);
                } catch (\Exception $e) {
                    Log::warning('Could not remove old role', [
                        'user_id' => $user->id,
                        'role'    => $oldRoleSlug,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }
        }

        if (isset($roleMapping[$newType]) && $this->roleExists($roleMapping[$newType]) && !$user->hasRole($roleMapping[$newType])) {
            $user->assignRole($roleMapping[$newType]);
        }

        if ($newType === User::TYPE_SANITATION_PERSONNEL) {
            $this->handleSanitationPersonnelFields($user, $data);
        }
    }

    // ==================================================================== //
    // ✅ FIX: softDeleteUser now PRESERVES all data for restore
    // ==================================================================== //
    /**
     * Soft delete a user.
     *
     * ✅ The user's PII is PRESERVED — name, email, phone, username all
     *    remain intact. Restore brings back a fully working login.
     *
     * PII is only scrubbed on FORCE delete (see UserManagementController).
     *
     * Prior behaviour (scrubbing on soft-delete) made users unrecoverable
     * because their login credentials were overwritten with tombstones.
     */
    public function softDeleteUser(User $user, User $deleter, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($user, $deleter, $reason) {

            // ---------------------------------------------------------
            // 1. Capture a snapshot for the audit trail.
            //    (Redundant now — the row itself is intact — but kept
            //    so downstream consumers relying on original_data still
            //    find it.)
            // ---------------------------------------------------------
            $snapshot = $this->getUserSnapshot($user);

            // ---------------------------------------------------------
            // 2. Write deletion metadata + column-level tracking.
            //    Data is NOT touched.
            // ---------------------------------------------------------
            $metadata = $user->metadata ?? [];
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true) ?? [];
            }
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $metadata['deletion_info'] = [
                'deleted_by'      => $deleter->id,
                'deleted_by_name' => $deleter->name,
                'deletion_reason' => $reason ?? 'Manual deletion by admin',
                'original_data'   => $snapshot,
                'deleted_at'      => now()->toISOString(),
            ];

            $user->forceFill([
                'deleted_by'      => $deleter->id,
                'deletion_reason' => $reason ?? 'Manual deletion by admin',
                'metadata'        => $metadata,
            ])->saveQuietly();

            // ---------------------------------------------------------
            // 3. Soft-delete (sets deleted_at). NO anonymization here.
            // ---------------------------------------------------------
            $deleted = $user->delete();

            Log::info('User soft deleted (PII preserved for restore)', [
                'user_id'          => $user->id,
                'deleted_by'       => $deleter->id,
                'deleted_by_name'  => $deleter->name,
                'reason'           => $reason,
                'preserved_email'  => $snapshot['email']  ?? null,
                'preserved_name'   => $snapshot['name']   ?? null,
            ]);

            return $deleted;
        });
    }

    public function getDashboardStats(User $currentUser, array $filters = []): array
    {
        $baseQuery = User::where('type', '!=', User::TYPE_DEVELOPER);
        $this->applyAuthorizationScope($baseQuery, $currentUser);
        $this->applyFilters($baseQuery, $filters);

        $landlordStats = $this->getLandlordStats($currentUser, $filters);

        return [
            'total_users'                  => (clone $baseQuery)->count(),
            'total_field_agents'           => (clone $baseQuery)
                ->where('type', User::TYPE_FIELD_AGENT)->count(),
            'total_landlords'              => $landlordStats['total'],
            'total_landlords_by_role'      => $landlordStats['total'],
            'property_owners'              => $this->getPropertyOwnerStats($currentUser, $filters),
            'landlords_without_properties' => $landlordStats['without_properties'],
            'landlord_breakdown'           => $this->getLandlordBreakdownByType($currentUser, $filters),
            'active_users'                 => (clone $baseQuery)
                ->where('status', User::STATUS_ACTIVE)->count(),
            'active_field_agents'          => (clone $baseQuery)
                ->where('type', User::TYPE_FIELD_AGENT)
                ->where('status', User::STATUS_ACTIVE)
                ->whereNotNull('invitation_accepted_at')
                ->count(),
            'pending_field_agents'         => (clone $baseQuery)
                ->where('type', User::TYPE_FIELD_AGENT)
                ->where('status', User::STATUS_PENDING)
                ->whereNull('invitation_accepted_at')
                ->count(),
            'former_landlords'             => (clone $baseQuery)
                ->where('type', User::TYPE_FORMER_LANDLORD)->count(),
            'old_deleted_users_count'      => $this->getOldDeletedUsersCount($currentUser),
            'oldest_deleted_days'          => $this->getOldestDeletedDays($currentUser),
            'multi_role_users'             => (clone $baseQuery)->has('roles', '>', 1)->count(),
            'total_tenants'                => (clone $baseQuery)
                ->where('type', User::TYPE_TENANT)->count(),
            'total_security_personnel'     => (clone $baseQuery)
                ->where('type', User::TYPE_SECURITY_PERSONNEL)->count(),
            'total_sanitation_personnel'   => (clone $baseQuery)
                ->where('type', User::TYPE_SANITATION_PERSONNEL)->count(),
            'total_contractors'            => (clone $baseQuery)
                ->where('type', User::TYPE_CONTRACTOR)->count(),
            'deleted_users'                => $this->getDeletedUserStats($currentUser),
            'archived_users'               => $this->getArchivedUserStats($currentUser),
        ];
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $typeConstant = $this->resolveUserTypeFilter($filters['type']);

            if ($typeConstant === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('type', $typeConstant);
            }
        }

        if (!empty($filters['role']) && $filters['role'] !== 'all') {
            $roleSlug = strtolower(trim($filters['role']));
            $query->whereHas('roles', function ($q) use ($roleSlug) {
                $q->where('slug', $roleSlug)
                  ->orWhere('slug', str_replace('_', '-', $roleSlug))
                  ->orWhere('slug', str_replace('-', '_', $roleSlug));
            });
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (isset($filters['phone_verified']) && $filters['phone_verified'] !== 'all') {
            if ($filters['phone_verified'] === 'verified') {
                $query->whereNotNull('phone_verified_at');
            } else {
                $query->whereNull('phone_verified_at');
            }
        }

        if (isset($filters['has_properties']) && $filters['has_properties'] !== 'all') {
            if ($filters['has_properties'] === 'yes') {
                $query->whereHas('ownedProperties');
            } else {
                $query->whereDoesntHave('ownedProperties');
            }
        }

        if (isset($filters['multi_role']) && $filters['multi_role'] === 'yes') {
            $query->has('roles', '>', 1);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }
    }

    protected function applyAuthorizationScope(Builder $query, User $currentUser): void
    {
        $query->where('type', '!=', User::TYPE_DEVELOPER);

        if (!$currentUser->isSuperAdmin()) {
            $query->where('type', '!=', User::TYPE_SUPER_ADMIN);
        }
    }

    protected function prepareUserData(array $data, User $creator): array
    {
        $userData = [
            'name'            => $data['name'],
            'email'           => $data['email'],
            'phone'           => $data['phone'],
            'type'            => (int) $data['type'],
            'status'          => $data['status'],
            'gender'          => $data['gender'] ?? null,
            'dob'             => $data['dob'] ?? null,
            'digital_address' => $data['digital_address'] ?? null,
            'region'          => $data['region'] ?? null,
            'location'        => $data['location'] ?? null,
            'username'        => $data['username'] ?? null,
            'created_by'      => $creator->id,
        ];

        if (!empty($data['send_invitation'])) {
            $userData['password'] = Hash::make(Str::random(32));
            $userData['status'] = User::STATUS_PENDING;
        } else {
            $userData['password'] = Hash::make($data['password']);
            $userData['email_verified_at'] = !empty($data['auto_verify_email']) ? now() : null;
        }

        if (!empty($data['auto_verify_phone'])) {
            $userData['phone_verified_at'] = now();
        }

        $userData['metadata'] = [
            'created_via'         => 'manual_by_admin',
            'registration_source' => 'admin_panel',
            'initial_status'      => $userData['status'],
            'created_by_role'     => $creator->isSuperAdmin() ? 'super_admin' : 'admin',
        ];

        return $userData;
    }

    // ==================== STATISTICS ====================

    protected function getFieldAgentStatistics(User $user): array
    {
        return [
            'total_assigned_plans'    => method_exists($user, 'assignedPlans') ? $user->assignedPlans()->count() : 0,
            'active_plans'            => method_exists($user, 'assignedPlans') ? $user->assignedPlans()->where('status', 'active')->count() : 0,
            'completed_plans'         => method_exists($user, 'assignedPlans') ? $user->assignedPlans()->whereHas('plan', fn($q) => $q->where('status', 'completed'))->count() : 0,
            'registered_properties'   => method_exists($user, 'registeredProperties') ? $user->registeredProperties()->count() : 0,
            'has_accepted_invitation' => !is_null($user->invitation_accepted_at),
        ];
    }

    protected function getLandlordStatistics(User $user): array
    {
        return [
            'total_properties'     => method_exists($user, 'properties') ? $user->properties()->count() : 0,
            'active_properties'    => method_exists($user, 'properties') ? $user->properties()->where('status', 'active')->count() : 0,
            'total_payments'       => method_exists($user, 'payments') ? $user->payments()->count() : 0,
            'total_payment_amount' => method_exists($user, 'payments') ? $user->payments()->sum('amount') : 0,
            'last_property_added'  => method_exists($user, 'properties') ? $user->properties()->latest()->first()?->created_at?->diffForHumans() : null,
        ];
    }

    protected function getTenantStatistics(User $user): array
    {
        return [
            'member_since'       => $user->created_at->diffForHumans(),
            'last_active'        => $user->last_activity_at?->diffForHumans() ?? 'Never',
            'profile_completion' => $this->calculateProfileCompletion($user),
            'rental_agreements'  => method_exists($user, 'rentalAgreements') ? $user->rentalAgreements()->count() : 0,
            'active_rentals'     => method_exists($user, 'rentalAgreements') ? $user->rentalAgreements()->where('status', 'active')->count() : 0,
        ];
    }

    protected function getAdminStatistics(User $user): array
    {
        return [
            'users_created' => User::where('created_by', $user->id)
                ->where('type', '!=', User::TYPE_DEVELOPER)
                ->when($user->type === User::TYPE_SUPER_ADMIN, fn($q) => $q->where('type', '!=', User::TYPE_SUPER_ADMIN))
                ->count(),
            'last_active'   => $user->last_activity_at?->diffForHumans() ?? 'Never',
            'total_logins'  => $user->metadata['total_logins'] ?? 0,
        ];
    }

    protected function getSecurityPersonnelStatistics(User $user): array
    {
        return [
            'member_since'                  => $user->created_at->diffForHumans(),
            'last_active'                   => $user->last_activity_at?->diffForHumans() ?? 'Never',
            'profile_completion'            => $this->calculateProfileCompletion($user),
            'supervisor_level'              => $user->supervisor_level,
            'can_be_supervisor'             => $user->can_be_supervisor,
            'total_schedules'               => method_exists($user, 'schedules') ? $user->schedules()->count() : 0,
            'active_supervisor_assignments' => method_exists($user, 'activeSupervisorAssignments') ? $user->activeSupervisorAssignments()->count() : 0,
            'is_security_supervisor'        => method_exists($user, 'isSecuritySupervisor') ? $user->isSecuritySupervisor() : false,
        ];
    }

    protected function getSanitationPersonnelStatistics(User $user): array
    {
        $sanitationPersonnel = $user->sanitationPersonnel;

        $stats = [
            'member_since'           => $user->created_at->diffForHumans(),
            'last_active'            => $user->last_activity_at?->diffForHumans() ?? 'Never',
            'profile_completion'     => $this->calculateProfileCompletion($user),
            'has_sanitation_profile' => !is_null($sanitationPersonnel),
        ];

        if ($sanitationPersonnel) {
            $stats = array_merge($stats, [
                'employee_id'        => $sanitationPersonnel->employee_id,
                'role'               => $sanitationPersonnel->role,
                'status'             => $sanitationPersonnel->status,
                'vehicle_number'     => $sanitationPersonnel->vehicle_number,
                'vehicle_type'       => $sanitationPersonnel->vehicle_type,
                'hire_date'          => $sanitationPersonnel->hire_date?->format('Y-m-d'),
                'is_supervisor'      => $sanitationPersonnel->isSupervisor(),
                'can_be_supervisor'  => $sanitationPersonnel->can_be_supervisor,
                'supervisor_id'      => $sanitationPersonnel->supervisor_id,
                'supervisor_name'    => $sanitationPersonnel->supervisor?->full_name,
                'team_size'          => $sanitationPersonnel->isSupervisor() ? $sanitationPersonnel->subordinates()->count() : 0,
                'total_workers'      => $sanitationPersonnel->role === 'supervisor' ? $sanitationPersonnel->workers()->count() : 0,
                'assigned_requests'  => method_exists($sanitationPersonnel, 'assignedRequests') ? $sanitationPersonnel->assignedRequests()->count() : 0,
                'active_requests'    => method_exists($sanitationPersonnel, 'activeRequests') ? $sanitationPersonnel->activeRequests()->count() : 0,
                'completed_requests' => method_exists($sanitationPersonnel, 'assignedRequests') ? $sanitationPersonnel->assignedRequests()->where('status', 'completed')->count() : 0,
            ]);
        }

        return $stats;
    }

    protected function getContractorStatistics(User $user): array
    {
        return [
            'member_since'        => $user->created_at->diffForHumans(),
            'last_active'         => $user->last_activity_at?->diffForHumans() ?? 'Never',
            'profile_completion'  => $this->calculateProfileCompletion($user),
            'total_contracts'     => method_exists($user, 'contracts') ? $user->contracts()->count() : 0,
            'total_workers'       => method_exists($user, 'constructionWorkers') ? $user->constructionWorkers()->count() : 0,
            'assigned_workers'    => method_exists($user, 'assignedWorkers') ? $user->assignedWorkers()->count() : 0,
            'active_contracts'    => method_exists($user, 'contracts') ? $user->contracts()->where('status', 'in_progress')->count() : 0,
            'completed_contracts' => method_exists($user, 'contracts') ? $user->contracts()->where('status', 'completed')->count() : 0,
        ];
    }

    protected function calculateProfileCompletion(User $user): int
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
        ];

        $completedFields = count(array_filter($fields));
        $totalFields = count($fields);

        return $totalFields > 0 ? round(($completedFields / $totalFields) * 100) : 0;
    }

    /**
     * Anonymize PII.
     *
     * ⚠️ This is now ONLY used by permanent delete flows — NOT soft-delete.
     * Scrubbing a soft-deleted user would destroy the login credentials
     * needed to restore them.
     */
    protected function anonymizeUserData(User $user): void
    {
        $user->update([
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
        ]);
    }

    /**
     * Snapshot the user's PII.
     */
    protected function getUserSnapshot(User $user): array
    {
        return [
            'name'     => $user->name,
            'email'    => $user->email,
            'phone'    => $user->phone,
            'username' => $user->username,
            'type'     => $user->type,
            'status'   => $user->status,
        ];
    }

    // ==================== COUNT HELPERS ====================

    protected function getFieldAgentStats(User $currentUser, array $filters = []): int
    {
        $query = User::where(function ($q) {
            $q->whereHas('roles', fn($rq) => $rq->where('slug', 'field-agent'))
              ->orWhere('type', User::TYPE_FIELD_AGENT);
        });
        $this->applyAuthorizationScope($query, $currentUser);
        $this->applyFilters($query, $filters);
        return $query->count();
    }

    protected function getLandlordStats(User $currentUser, array $filters = []): array
    {
        $query = User::where(function ($q) {
            $q->whereHas('roles', fn($rq) => $rq->where('slug', 'landlord'))
              ->orWhere('type', User::TYPE_LANDLORD);
        })
        ->where('type', '!=', User::TYPE_DEVELOPER);

        $this->applyFilters($query, $filters);

        $total = $query->count();
        $withProperties = (clone $query)->whereHas('properties', fn($q) => $q->whereNull('deleted_at'))->count();

        return [
            'total'              => $total,
            'with_properties'    => $withProperties,
            'without_properties' => $total - $withProperties,
        ];
    }

    protected function getPropertyOwnerStats(User $currentUser, array $filters = []): int
    {
        $query = User::where(function ($q) {
            $q->whereHas('roles', fn($rq) => $rq->where('slug', 'landlord'))
              ->orWhere('type', User::TYPE_LANDLORD);
        })
        ->whereHas('properties', fn($q) => $q->whereNull('deleted_at'))
        ->where('type', '!=', User::TYPE_DEVELOPER);

        $this->applyAuthorizationScope($query, $currentUser);
        $this->applyFilters($query, $filters);
        return $query->count();
    }

    protected function getLandlordBreakdownByType(User $currentUser, array $filters = []): array
    {
        $baseCondition = function ($q) {
            $q->whereHas('roles', fn($rq) => $rq->where('slug', 'landlord'))
              ->orWhere('type', User::TYPE_LANDLORD);
        };

        $breakdown = [
            'by_type' => [
                'super_admin'          => 0,
                'admin'                => 0,
                'field_agent'          => 0,
                'security_personnel'   => 0,
                'tenant'               => 0,
                'landlord'             => 0,
                'sanitation_personnel' => 0,
                'contractor'           => 0,
            ],
        ];

        $slugs = [
            'super-admin'          => 'super_admin',
            'admin'                => 'admin',
            'field-agent'          => 'field_agent',
            'security-personnel'   => 'security_personnel',
            'tenant'               => 'tenant',
            'sanitation-personnel' => 'sanitation_personnel',
            'contractor'           => 'contractor',
        ];

        foreach ($slugs as $slug => $key) {
            $q = User::where($baseCondition)
                ->whereHas('roles', fn($rq) => $rq->where('slug', $slug))
                ->where('type', '!=', User::TYPE_DEVELOPER);
            $this->applyFilters($q, $filters);
            $breakdown['by_type'][$key] = $q->count();
        }

        $q = User::where('type', User::TYPE_LANDLORD)
            ->whereDoesntHave('roles', fn($rq) => $rq->where('slug', 'landlord'))
            ->where('type', '!=', User::TYPE_DEVELOPER);
        $this->applyFilters($q, $filters);
        $breakdown['by_type']['landlord'] = $q->count();

        return $breakdown;
    }

    protected function getActiveUsersCount(User $currentUser): int
    {
        $query = User::where('status', User::STATUS_ACTIVE)
            ->where('type', '!=', User::TYPE_DEVELOPER);
        $this->applyAuthorizationScope($query, $currentUser);
        return $query->count();
    }

    protected function getActiveFieldAgentsCount(User $currentUser): int
    {
        $query = User::where(function ($q) {
            $q->whereHas('roles', fn($rq) => $rq->where('slug', 'field-agent'))
              ->orWhere('type', User::TYPE_FIELD_AGENT);
        })
        ->where('status', User::STATUS_ACTIVE)
        ->whereNotNull('invitation_accepted_at')
        ->where('type', '!=', User::TYPE_DEVELOPER);

        $this->applyAuthorizationScope($query, $currentUser);
        return $query->count();
    }

    protected function getPendingFieldAgentsCount(User $currentUser): int
    {
        $query = User::where(function ($q) {
            $q->whereHas('roles', fn($rq) => $rq->where('slug', 'field-agent'))
              ->orWhere('type', User::TYPE_FIELD_AGENT);
        })
        ->where('status', User::STATUS_PENDING)
        ->whereNull('invitation_accepted_at')
        ->where('type', '!=', User::TYPE_DEVELOPER);

        $this->applyAuthorizationScope($query, $currentUser);
        return $query->count();
    }

    protected function getFormerLandlordsCount(User $currentUser): int
    {
        $query = User::where('type', User::TYPE_FORMER_LANDLORD)
            ->where('type', '!=', User::TYPE_DEVELOPER);
        $this->applyAuthorizationScope($query, $currentUser);
        return $query->count();
    }

    protected function getOldDeletedUsersCount(User $currentUser): int
    {
        $query = User::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays(30))
            ->where('type', '!=', User::TYPE_DEVELOPER);
        $this->applyAuthorizationScope($query, $currentUser);
        return $query->count();
    }

    protected function getOldestDeletedDays(User $currentUser): ?int
    {
        $oldest = User::onlyTrashed()
            ->where('type', '!=', User::TYPE_DEVELOPER)
            ->orderBy('deleted_at', 'asc')
            ->first();

        if ($oldest && $oldest->deleted_at) {
            // ✅ Cast to int — Carbon 3 returns a float
            return (int) $oldest->deleted_at->diffInDays(now());
        }

        return null;
    }

    protected function getMultiRoleUserStats(User $currentUser): int
    {
        $query = User::has('roles', '>', 1)->where('type', '!=', User::TYPE_DEVELOPER);
        $this->applyAuthorizationScope($query, $currentUser);
        return $query->count();
    }

    protected function getTenantStats(User $currentUser): int
    {
        $query = User::where(function ($q) {
            $q->whereHas('roles', fn($rq) => $rq->where('slug', 'tenant'))
              ->orWhere('type', User::TYPE_TENANT);
        });
        $this->applyAuthorizationScope($query, $currentUser);
        return $query->count();
    }

    protected function getSecurityPersonnelStats(User $currentUser): int
    {
        $query = User::where(function ($q) {
            $q->whereHas('roles', fn($rq) => $rq->where('slug', 'security-personnel'))
              ->orWhere('type', User::TYPE_SECURITY_PERSONNEL);
        });
        $this->applyAuthorizationScope($query, $currentUser);
        return $query->count();
    }

    protected function getSanitationPersonnelStats(User $currentUser): int
    {
        $query = User::where(function ($q) {
            $q->whereHas('roles', fn($rq) => $rq->where('slug', 'sanitation-personnel'))
              ->orWhere('type', User::TYPE_SANITATION_PERSONNEL);
        });
        $this->applyAuthorizationScope($query, $currentUser);
        return $query->count();
    }

    protected function getContractorStats(User $currentUser): int
    {
        $query = User::where(function ($q) {
            $q->whereHas('roles', fn($rq) => $rq->where('slug', 'contractor'))
              ->orWhere('type', User::TYPE_CONTRACTOR);
        });
        $this->applyAuthorizationScope($query, $currentUser);
        return $query->count();
    }

    protected function getDeletedUserStats(User $currentUser): int
    {
        $query = User::onlyTrashed()->where('type', '!=', User::TYPE_DEVELOPER);
        $this->applyAuthorizationScope($query, $currentUser);
        return $query->count();
    }

    protected function getArchivedUserStats(User $currentUser): int
    {
        $query = User::where('status', User::STATUS_ARCHIVED);
        $this->applyAuthorizationScope($query, $currentUser);
        return $query->count();
    }

    // ==================== DROPDOWNS ====================

    public function getSanitationPersonnelList(User $currentUser): array
    {
        $query = User::where(function ($q) {
            $q->whereHas('roles', fn($rq) => $rq->where('slug', 'sanitation-personnel'))
              ->orWhere('type', User::TYPE_SANITATION_PERSONNEL);
        })
        ->where('status', User::STATUS_ACTIVE)
        ->where('type', '!=', User::TYPE_DEVELOPER);

        $this->applyAuthorizationScope($query, $currentUser);

        return $query->orderBy('name')
            ->get()
            ->mapWithKeys(function ($user) {
                $zone = $user->sanitationPersonnel?->assigned_zone ?? 'Unassigned';
                return [$user->id => $user->name . ' (' . $zone . ')'];
            })
            ->toArray();
    }

    public function getSanitationPersonnelWithZones(User $currentUser): Collection
    {
        $query = User::where(function ($q) {
            $q->whereHas('roles', fn($rq) => $rq->where('slug', 'sanitation-personnel'))
              ->orWhere('type', User::TYPE_SANITATION_PERSONNEL);
        })
        ->where('status', User::STATUS_ACTIVE)
        ->where('type', '!=', User::TYPE_DEVELOPER)
        ->with('sanitationPersonnel');

        $this->applyAuthorizationScope($query, $currentUser);

        return $query->orderBy('name')->get();
    }

    public function getAvailableSanitationSupervisors(User $currentUser): array
    {
        return SanitationPersonnel::query()
            ->where(function ($q) {
                $q->whereIn('role', SanitationPersonnel::SUPERVISOR_ROLES)
                  ->orWhere('can_be_supervisor', true);
            })
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(function ($personnel) {
                $label = $personnel->full_name
                    . ' (' . ucfirst($personnel->role) . ')'
                    . ($personnel->employee_id ? ' · ' . $personnel->employee_id : '');

                return [$personnel->id => $label];
            })
            ->toArray();
    }
}
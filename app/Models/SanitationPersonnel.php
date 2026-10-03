<?php
// app/Models/SanitationPersonnel.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SanitationPersonnel extends Model
{
    use HasFactory, SoftDeletes;

    // ================================================================ //
    // 📌 TABLE NAME                                                    //
    // ================================================================ //

    protected $table = 'sanitation_personnels';

    public const TABLE = 'sanitation_personnels';

    // ================================================================ //
    // 📌 ROLE CONSTANTS                                                //
    // ================================================================ //

    public const SUPERVISOR_ROLES = [
        'supervisor',
        'team-lead',
        'sanitation-supervisor',
        'manager',
    ];

    public const PERSONNEL_ROLES = [
        'collector',
        'driver',
        'sanitation-personnel',
        'worker',
    ];

    public const MAX_CHAIN_DEPTH = 20;

    public const MAX_ACTIVE_JOBS = 3;

    // ================================================================ //
    // 🧾 FILLABLE                                                      //
    // ================================================================ //

    protected $fillable = [
        'user_id',
        'supervisor_id',
        'can_be_supervisor',

        'employee_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'address',
        'profile_photo',

        'status',
        'role',

        'vehicle_number',
        'vehicle_type',

        'latitude',
        'longitude',
        'last_location_update',
        'availability_schedule',
        'assigned_zone',

        'emergency_contact',
        'hire_date',
        'certifications',
        'shift_preference',
    ];

    // ================================================================ //
    // 🎭 CASTS                                                         //
    // ================================================================ //

    protected $casts = [
        'availability_schedule' => 'array',
        'assigned_zone'         => 'array',
        'certifications'        => 'array',
        'latitude'              => 'decimal:8',
        'longitude'             => 'decimal:8',
        'last_location_update'  => 'datetime',
        'hire_date'             => 'datetime',
        'can_be_supervisor'     => 'boolean',
    ];

    // ================================================================ //
    // 🪝 MODEL EVENTS                                                  //
    // ================================================================ //

    /**
     * Cascade the soft-delete to the linked User and record who
     * performed the deletion.
     *
     * This runs BEFORE the personnel row is deleted. It:
     *   1. Skips if the linked User doesn't exist
     *   2. Skips if the linked User is an admin/super-admin/developer
     *   3. Skips if the linked User owns properties (they'd lose access)
     *   4. Skips if the linked User has Spatie roles other than sanitation
     *   5. Skips if the linked User is the current authenticated user
     *   6. Otherwise:
     *        - writes deleted_by + deletion_reason onto the User
     *        - soft-deletes the User
     *
     * Because this is a `deleting` hook, it fires regardless of whether
     * the delete originated from a controller, a bulk action, a queue
     * job, or tinker — the metadata is always written.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $personnel) {
            $linkedUser = $personnel->user;

            if (!$linkedUser) {
                Log::info('SanitationPersonnel deleting: no linked user to cascade', [
                    'personnel_id' => $personnel->id,
                ]);
                return;
            }

            // -----------------------------------------------------------------
            // Safety: never cascade onto admins / super-admins / developers
            // -----------------------------------------------------------------
            $protectedTypes = [
                User::TYPE_ADMIN,
                User::TYPE_SUPER_ADMIN,
                User::TYPE_DEVELOPER,
            ];

            if (in_array($linkedUser->type, $protectedTypes, true)) {
                Log::info('SanitationPersonnel deleting: linked user is protected, skipping cascade', [
                    'personnel_id' => $personnel->id,
                    'user_id'      => $linkedUser->id,
                    'user_type'    => $linkedUser->type,
                ]);
                return;
            }

            // -----------------------------------------------------------------
            // Safety: never cascade onto the currently authenticated user
            // -----------------------------------------------------------------
            if (auth()->check() && $linkedUser->id === auth()->id()) {
                Log::info('SanitationPersonnel deleting: linked user is the current user, skipping cascade', [
                    'personnel_id' => $personnel->id,
                    'user_id'      => $linkedUser->id,
                ]);
                return;
            }

            // -----------------------------------------------------------------
            // Safety: never cascade if the linked user owns properties
            // (they'd lose access to manage them)
            // -----------------------------------------------------------------
            try {
                if ($linkedUser->properties()->exists()) {
                    Log::info('SanitationPersonnel deleting: linked user owns properties, skipping cascade', [
                        'personnel_id' => $personnel->id,
                        'user_id'      => $linkedUser->id,
                    ]);
                    return;
                }
            } catch (\Throwable $e) {
                // If the relationship isn't defined, ignore — err on the side of caution
                Log::warning('SanitationPersonnel cascade: could not check properties()', [
                    'personnel_id' => $personnel->id,
                    'error'        => $e->getMessage(),
                ]);
            }

            // -----------------------------------------------------------------
            // Safety: never cascade if the linked user has Spatie roles
            // (they wear another hat — landlord, tenant, etc.)
            // -----------------------------------------------------------------
            try {
                if ($linkedUser->roles()->exists()) {
                    Log::info('SanitationPersonnel deleting: linked user has other roles, skipping cascade', [
                        'personnel_id' => $personnel->id,
                        'user_id'      => $linkedUser->id,
                        'roles'        => $linkedUser->roles()->pluck('slug')->toArray(),
                    ]);
                    return;
                }
            } catch (\Throwable $e) {
                Log::warning('SanitationPersonnel cascade: could not check roles()', [
                    'personnel_id' => $personnel->id,
                    'error'        => $e->getMessage(),
                ]);
            }

            // -----------------------------------------------------------------
            // Record who deleted + why, on the User record
            // -----------------------------------------------------------------
            $actor     = auth()->user();
            $actorId   = $actor?->id;
            $actorName = $actor?->name ?? 'System';

            try {
                $linkedUser->forceFill([
                    'status'          => User::STATUS_INACTIVE,
                    'deleted_by'      => $actorId,
                    'deletion_reason' => 'Sanitation personnel record deleted by ' . $actorName,
                ])->save();
            } catch (\Throwable $e) {
                Log::error('SanitationPersonnel cascade: failed to update linked user metadata', [
                    'personnel_id' => $personnel->id,
                    'user_id'      => $linkedUser->id,
                    'error'        => $e->getMessage(),
                ]);
            }

            // -----------------------------------------------------------------
            // Soft-delete the User (respects SoftDeletes trait)
            // -----------------------------------------------------------------
            try {
                $linkedUser->delete();

                Log::info('SanitationPersonnel cascade: linked user soft-deleted', [
                    'personnel_id'   => $personnel->id,
                    'user_id'        => $linkedUser->id,
                    'user_name'      => $linkedUser->name,
                    'deleted_by'     => $actorId,
                    'deleted_by_name'=> $actorName,
                ]);
            } catch (\Throwable $e) {
                Log::error('SanitationPersonnel cascade: failed to soft-delete linked user', [
                    'personnel_id' => $personnel->id,
                    'user_id'      => $linkedUser->id,
                    'error'        => $e->getMessage(),
                ]);
            }
        });
    }

    // ================================================================ //
    // 🔗 RELATIONSHIPS                                                 //
    // ================================================================ //

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(SanitationPersonnel::class, 'supervisor_id');
    }

    public function subordinates()
    {
        return $this->hasMany(SanitationPersonnel::class, 'supervisor_id');
    }

    public function assignedRequests()
    {
        return $this->hasMany(WasteCollectionRequest::class, 'assigned_to');
    }

    public function workers()
    {
        return $this->hasMany(SanitationWorker::class, 'supervisor_id');
    }

    public function activeRequests()
    {
        return $this->assignedRequests()
            ->whereIn('status', ['assigned', 'en_route', 'arrived', 'in_progress']);
    }

    // ================================================================ //
    // 🔍 SCOPES                                                        //
    // ================================================================ //

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeAvailable($query)
    {
        return $query->active()
            ->whereHas('activeRequests', function ($q) {
                $q->whereIn('status', ['assigned', 'en_route', 'arrived', 'in_progress']);
            }, '<', self::MAX_ACTIVE_JOBS);
    }

    public function scopeSupervisors($query)
    {
        return $query->whereIn('role', self::SUPERVISOR_ROLES);
    }

    public function scopePersonnel($query)
    {
        return $query->whereIn('role', self::PERSONNEL_ROLES);
    }

    public function scopeReportingTo($query, int $supervisorId)
    {
        return $query->where('supervisor_id', $supervisorId);
    }

    public function scopeEligibleSupervisors($query)
    {
        return $query->where('can_be_supervisor', true)
                     ->where('status', 'active');
    }

    public function scopeRootSupervisors($query)
    {
        return $query->whereNull('supervisor_id')
                     ->whereIn('role', self::SUPERVISOR_ROLES);
    }

    public function scopeTopLevel($query)
    {
        return $query->rootSupervisors();
    }

    public function scopeNearLocation($query, $lat, $lng, $radius = 10)
    {
        return $query->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereRaw(
                "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?",
                [$lat, $lng, $lat, $radius]
            );
    }

    // ================================================================ //
    // 🧮 ACCESSORS                                                     //
    // ================================================================ //

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getCurrentLocationAttribute(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [
                'lat'         => $this->latitude,
                'lng'         => $this->longitude,
                'last_update' => $this->last_location_update,
            ];
        }

        return null;
    }

    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            'active'    => 'success',
            'inactive'  => 'danger',
            'on_leave'  => 'warning',
            'suspended' => 'dark',
        ];

        return $badges[$this->status] ?? 'secondary';
    }

    public function getIsAvailableAttribute(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->canAcceptMoreJobs();
    }

    public function getAvailabilityBadgeAttribute(): string
    {
        if ($this->status !== 'active') {
            return 'secondary';
        }

        return $this->canAcceptMoreJobs() ? 'success' : 'warning';
    }

    public function getAvailabilityLabelAttribute(): string
    {
        if ($this->status !== 'active') {
            return ucfirst(str_replace('_', ' ', $this->status));
        }

        return $this->canAcceptMoreJobs() ? 'Available' : 'At Capacity';
    }

    public function getActiveJobsCountAttribute(): int
    {
        return $this->activeRequests()->count();
    }

    public function getCompletedJobsTodayAttribute(): int
    {
        return $this->assignedRequests()
            ->where('status', 'completed')
            ->whereDate('completed_at', today())
            ->count();
    }

    public function getSupervisorUserAttribute(): ?User
    {
        return $this->supervisor?->user;
    }

    public function getTeamSummaryAttribute(): array
    {
        if (!$this->isSupervisor()) {
            return [
                'is_supervisor'  => false,
                'team_size'      => 0,
                'active_members' => 0,
                'total_workload' => 0,
            ];
        }

        $team = $this->subordinates;

        return [
            'is_supervisor'  => true,
            'team_size'      => $team->count(),
            'active_members' => $team->where('status', 'active')->count(),
            'total_workload' => $team->sum(fn ($p) => $p->active_jobs_count),
        ];
    }

    public function getAncestorsAttribute(): Collection
    {
        return $this->ancestors();
    }

    public function getDescendantsAttribute(): Collection
    {
        return $this->descendants();
    }

    public function getChainDepthAttribute(): int
    {
        return $this->chainDepth();
    }

    public function getSupervisorChainAttribute(): array
    {
        $chain = $this->ancestors()->reverse()->values();

        return $chain->pluck('full_name')
            ->push($this->full_name)
            ->filter()
            ->values()
            ->toArray();
    }

    public function getTeamTreeAttribute(): array
    {
        return $this->buildTeamTree();
    }

    // ================================================================ //
    // 🛠️ METHODS                                                       //
    // ================================================================ //

    public function updateLocation($lat, $lng): bool
    {
        return $this->update([
            'latitude'             => $lat,
            'longitude'            => $lng,
            'last_location_update' => now(),
        ]);
    }

    public function getAvailablePersonnel($radius = 10)
    {
        if ($this->latitude && $this->longitude) {
            return self::available()
                ->nearLocation($this->latitude, $this->longitude, $radius)
                ->where('id', '!=', $this->id)
                ->get();
        }

        return collect();
    }

    public function canAcceptMoreJobs(): bool
    {
        return $this->activeRequests()->count() < self::MAX_ACTIVE_JOBS;
    }

    public function isSupervisor(): bool
    {
        return in_array($this->role, self::SUPERVISOR_ROLES, true);
    }

    public function isPersonnel(): bool
    {
        return in_array($this->role, self::PERSONNEL_ROLES, true);
    }

    public function isRootSupervisor(): bool
    {
        return $this->isSupervisor() && is_null($this->supervisor_id);
    }

    // ================================================================ //
    // 🌳 HIERARCHY WALKERS                                             //
    // ================================================================ //

    public function ancestors(): Collection
    {
        $ancestors = collect();
        $seenIds   = [$this->id];
        $current   = $this->supervisor;
        $depth     = 0;

        while ($current && $depth < self::MAX_CHAIN_DEPTH) {
            if (in_array($current->id, $seenIds, true)) {
                Log::warning('Cycle detected in sanitation personnel chain', [
                    'personnel_id' => $this->id,
                    'cycle_at'     => $current->id,
                ]);
                break;
            }

            $ancestors->push($current);
            $seenIds[] = $current->id;
            $current   = $current->supervisor;
            $depth++;
        }

        return $ancestors;
    }

    public function descendants(): Collection
    {
        $descendants = collect();
        $queue       = $this->subordinates()->get();

        while ($queue->isNotEmpty()) {
            $child = $queue->shift();

            if ($descendants->contains('id', $child->id)) {
                continue;
            }

            $descendants->push($child);
            $queue = $queue->merge($child->subordinates()->get());
        }

        return $descendants;
    }

    public function chainDepth(): int
    {
        return $this->ancestors()->count();
    }

    public function wouldCreateCycle(?int $newSupervisorId): bool
    {
        if (!$newSupervisorId) {
            return false;
        }

        if ($newSupervisorId === $this->id) {
            return true;
        }

        $seen    = [];
        $current = static::find($newSupervisorId);

        while ($current) {
            if ($current->id === $this->id) {
                return true;
            }

            if (in_array($current->id, $seen, true)) {
                return true;
            }

            $seen[] = $current->id;
            $current = $current->supervisor;
        }

        return false;
    }

    // ================================================================ //
    // 🔔 NOTIFICATIONS                                                 //
    // ================================================================ //

    public function notifiableUsers(): Collection
    {
        $users       = collect();
        $seenUserIds = [];

        if ($this->user && !in_array($this->user->id, $seenUserIds, true)) {
            $users->push($this->user);
            $seenUserIds[] = $this->user->id;
        }

        foreach ($this->ancestors() as $ancestor) {
            if (!$ancestor->user) {
                continue;
            }

            if (in_array($ancestor->user->id, $seenUserIds, true)) {
                continue;
            }

            $users->push($ancestor->user);
            $seenUserIds[] = $ancestor->user->id;
        }

        return $users->filter()->values();
    }

    public function notifiableUserIds(): Collection
    {
        return $this->notifiableUsers()->pluck('id');
    }

    // ================================================================ //
    // 📊 STATS                                                         //
    // ================================================================ //

    public function getDailyStats(): array
    {
        return [
            'assigned'     => $this->assignedRequests()->whereDate('created_at', today())->count(),
            'in_progress'  => $this->assignedRequests()->where('status', 'in_progress')->count(),
            'completed'    => $this->assignedRequests()
                ->where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
            'total_weight' => $this->assignedRequests()
                ->where('status', 'completed')
                ->whereDate('completed_at', today())
                ->sum('waste_weight_kg'),
        ];
    }

    public function getWeeklyStats(): array
    {
        $startOfWeek = now()->startOfWeek();
        $endOfWeek   = now()->endOfWeek();

        return [
            'total_jobs'          => $this->assignedRequests()
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->count(),
            'completed_jobs'      => $this->assignedRequests()
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startOfWeek, $endOfWeek])
                ->count(),
            'total_weight'        => $this->assignedRequests()
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startOfWeek, $endOfWeek])
                ->sum('waste_weight_kg'),
            'avg_completion_time' => $this->assignedRequests()
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startOfWeek, $endOfWeek])
                ->avg('completion_time'),
        ];
    }

    // ================================================================ //
    // 🔒 PRIVATE HELPERS                                               //
    // ================================================================ //

    private function buildTeamTree(int $depth = 0): array
    {
        if ($depth >= self::MAX_CHAIN_DEPTH) {
            return [];
        }

        return $this->subordinates()
            ->with(['user'])
            ->orderBy('first_name')
            ->get()
            ->map(function (self $child) use ($depth) {
                return [
                    'id'            => $child->id,
                    'name'          => $child->full_name,
                    'role'          => $child->role,
                    'status'        => $child->status,
                    'employee_id'   => $child->employee_id,
                    'active_jobs'   => $child->active_jobs_count,
                    'is_supervisor' => $child->isSupervisor(),
                    'children'      => $child->buildTeamTree($depth + 1),
                ];
            })
            ->all();
    }
}
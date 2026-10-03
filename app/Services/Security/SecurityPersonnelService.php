<?php

namespace App\Services\Security;

use App\Models\User;
use App\Models\SecurityPost;
use App\Models\SecuritySupervisorAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SecurityPersonnelService
{
    /**
     * Get security personnel with their assignments
     */
    public function getPersonnelWithAssignments(array $filters = [])
    {
        $query = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->with(['supervisorAssignments' => function($q) {
                $q->where('is_active', true);
            }]);

        if (!empty($filters['post_id'])) {
            $query->whereHas('supervisorAssignments', function($q) use ($filters) {
                $q->where('security_post_id', $filters['post_id'])
                  ->where('is_active', true);
            });
        }

        if (!empty($filters['supervisor_level'])) {
            $query->where('supervisor_level', $filters['supervisor_level']);
        }

        return $query->get();
    }

    /**
     * Assign security personnel to a post
     */
    public function assignToPost(User $personnel, int $postId, User $assignedBy): bool
    {
        try {
            DB::beginTransaction();

            // Deactivate existing assignments
            SecuritySupervisorAssignment::where('user_id', $personnel->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // Create new assignment
            SecuritySupervisorAssignment::create([
                'user_id' => $personnel->id,
                'security_post_id' => $postId,
                'assigned_by' => $assignedBy->id,
                'start_date' => now(),
                'supervisor_type' => 'post_supervisor',
                'is_active' => true,
                'is_primary_supervisor' => false,
                'metadata' => [
                    'assigned_by_name' => $assignedBy->name,
                    'assigned_at' => now()->toISOString()
                ]
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign personnel to post: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove personnel from a post
     */
    public function removeFromPost(User $personnel, int $postId): bool
    {
        try {
            return SecuritySupervisorAssignment::where('user_id', $personnel->id)
                ->where('security_post_id', $postId)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        } catch (\Exception $e) {
            Log::error('Failed to remove personnel from post: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get supervisor statistics
     */
    public function getSupervisorStats(User $areaSupervisor): array
    {
        $postIds = $this->getAccessiblePostIds($areaSupervisor);

        return [
            'total_personnel' => User::where('type', User::TYPE_SECURITY_PERSONNEL)
                ->whereHas('supervisorAssignments', function($q) use ($postIds) {
                    $q->whereIn('security_post_id', $postIds)
                      ->where('is_active', true);
                })->count(),
            
            'total_supervisors' => User::where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('can_be_supervisor', true)
                ->whereHas('supervisorAssignments', function($q) use ($postIds) {
                    $q->whereIn('security_post_id', $postIds)
                      ->where('is_active', true);
                })->count(),
            
            'team_leads' => User::where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('supervisor_level', 1)
                ->whereHas('supervisorAssignments', function($q) use ($postIds) {
                    $q->whereIn('security_post_id', $postIds)
                      ->where('is_active', true);
                })->count(),
            
            'section_leads' => User::where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('supervisor_level', 2)
                ->whereHas('supervisorAssignments', function($q) use ($postIds) {
                    $q->whereIn('security_post_id', $postIds)
                      ->where('is_active', true);
                })->count(),
        ];
    }

    /**
     * Get accessible post IDs for a supervisor
     */
    private function getAccessiblePostIds(User $areaSupervisor): array
    {
        $assignments = SecuritySupervisorAssignment::where('user_id', $areaSupervisor->id)
            ->where('is_active', true)
            ->get();

        if ($assignments->isEmpty()) {
            return [];
        }

        $hasAllPosts = $assignments->contains(function($assignment) {
            return is_null($assignment->security_post_id);
        });

        if ($hasAllPosts) {
            return SecurityPost::active()->pluck('id')->toArray();
        }

        return $assignments->pluck('security_post_id')->filter()->unique()->toArray();
    }

    /**
     * Validate if personnel can be assigned as supervisor
     */
    public function canBeSupervisor(User $personnel): bool
    {
        // Must be security personnel
        if ($personnel->type !== User::TYPE_SECURITY_PERSONNEL) {
            return false;
        }

        // Must have active status
        if ($personnel->status !== User::STATUS_ACTIVE) {
            return false;
        }

        // Check if already a supervisor
        if ($personnel->supervisor_level > 0) {
            return false;
        }

        return true;
    }

    /**
     * Get available supervisor levels
     */
    public function getAvailableSupervisorLevels(User $currentUser): array
    {
        $levels = [];

        if ($this->isAreaSupervisor($currentUser)) {
            $levels = [
                1 => 'Team Lead (Level 1)',
                2 => 'Section Lead (Level 2)',
            ];
        }

        if ($currentUser->hasRole('super-admin')) {
            $levels[3] = 'Post Commander (Level 3)';
        }

        return $levels;
    }

    /**
     * Check if user is an area supervisor
     */
    private function isAreaSupervisor(User $user): bool
    {
        return $user->hasRole('area_supervisor') || 
               SecuritySupervisorAssignment::where('user_id', $user->id)
                   ->where(function($q) {
                       $q->where('supervisor_type', 'area_supervisor')
                         ->orWhere('supervisor_type', 'post_commander');
                   })
                   ->where('is_active', true)
                   ->exists();
    }
}
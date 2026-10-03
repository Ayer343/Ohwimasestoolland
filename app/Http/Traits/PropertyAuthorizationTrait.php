<?php

namespace App\Http\Traits;

use App\Models\User;
use App\Models\PlanAgentAssignment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

trait PropertyAuthorizationTrait
{
    /**
     * Check if user can create property
     */
    private function canCreateProperty($request)
    {
        $user = auth()->user();
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isFieldAgent();
    }

    /**
     * Check if user can view property
     */
    private function canViewProperty($property)
    {
        $user = auth()->user();
        
        // Super admins and admins can always view
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }
        
        // Landlords can view their own properties
        if ($user->isLandlord()) {
            return $property->landlord_id == $user->id;
        }
        
        if (Gate::allows('view', $property)) {
            return true;
        }
        
        // Field agents can view properties they registered or are assigned to
        if ($user->isFieldAgent()) {
            $canView = $property->registered_by == $user->id ||
                      $property->registrationPlan->planAssignments()
                          ->where('agent_id', $user->id)
                          ->where('is_active', true)
                          ->exists();
            
            return $canView;
        }
        
        return false;
    }

    /**
     * Check if user can update property
     * This is the critical method for photo uploads
     */
    private function canUpdateProperty($property)
    {
        $user = auth()->user();
        
        // Log the check for debugging
        Log::info('canUpdateProperty check', [
            'user_id' => $user->id,
            'user_type' => $user->type,
            'user_roles' => $user->roles->pluck('name')->toArray(),
            'is_landlord' => $user->isLandlord(),
            'property_id' => $property->id,
            'property_landlord_id' => $property->landlord_id,
            'is_owner' => $property->landlord_id == $user->id
        ]);
        
        // Super admins and admins can always update
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            Log::info('canUpdateProperty: Admin/SuperAdmin access granted');
            return true;
        }
        
        // Landlords can update their own properties
        if ($user->isLandlord()) {
            $isOwner = $property->landlord_id == $user->id;
            Log::info('canUpdateProperty: Landlord check', [
                'is_owner' => $isOwner,
                'property_landlord_id' => $property->landlord_id,
                'user_id' => $user->id
            ]);
            return $isOwner;
        }
        
        // Check Gate permissions
        if (Gate::allows('update', $property)) {
            Log::info('canUpdateProperty: Gate permission granted');
            return true;
        }
        
        // Field agents can only update properties they registered
        if ($user->isFieldAgent()) {
            $isRegisteredBy = $property->registered_by == $user->id;
            Log::info('canUpdateProperty: Field Agent check', [
                'is_registered_by' => $isRegisteredBy,
                'registered_by' => $property->registered_by,
                'user_id' => $user->id
            ]);
            return $isRegisteredBy;
        }
        
        Log::warning('canUpdateProperty: Access denied', [
            'user_id' => $user->id,
            'user_type' => $user->type,
            'property_id' => $property->id
        ]);
        
        return false;
    }

    /**
     * Check if user can delete property
     */
    private function canDeleteProperty($property)
    {
        $user = auth()->user();
        
        // Super admins and admins can always delete
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }
        
        // Landlords cannot delete properties (only admins can)
        if ($user->isLandlord()) {
            Log::info('canDeleteProperty: Landlord denied - only admins can delete');
            return false;
        }
        
        // Field agents can only delete properties they registered
        if ($user->isFieldAgent()) {
            return $property->registered_by == $user->id;
        }
        
        return false;
    }

    /**
     * Check if landlord owns a property
     */
    private function isLandlordOwner($property)
    {
        $user = auth()->user();
        $isOwner = $user->isLandlord() && $property->landlord_id == $user->id;
        
        Log::info('isLandlordOwner check', [
            'user_id' => $user->id,
            'is_landlord' => $user->isLandlord(),
            'property_landlord_id' => $property->landlord_id,
            'is_owner' => $isOwner
        ]);
        
        return $isOwner;
    }

    /**
     * Check if agent is assigned to plan
     */
    private function isAgentAssignedToPlan($agentId, $planId)
    {
        return PlanAgentAssignment::where('agent_id', $agentId)
            ->where('plan_id', $planId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Apply field agent scope to query
     */
    private function applyFieldAgentScope($query)
    {
        $user = auth()->user();
        
        if ($user->isFieldAgent()) {
            $query->where(function($q) use ($user) {
                $q->where('registered_by', $user->id)
                  ->orWhereHas('registrationPlan', function($planQuery) use ($user) {
                      $planQuery->whereHas('planAssignments', function($assignmentQuery) use ($user) {
                          $assignmentQuery->where('agent_id', $user->id)
                                         ->where('is_active', true);
                      });
                  });
            });
        }
    }

    /**
     * Apply landlord scope to query (only show their own properties)
     */
    private function applyLandlordScope($query)
    {
        $user = auth()->user();
        
        if ($user->isLandlord()) {
            $query->where('landlord_id', $user->id);
        }
    }

    /**
     * Get registration plans based on user type
     */
    protected function getRegistrationPlansForUser()
    {
        $user = auth()->user();
        
        // For admins and super admins - get all active plans
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return \App\Models\RegistrationPlan::whereNull('deleted_at')
                ->whereIn('status', ['assigned', 'in_progress', 'draft'])
                ->withCount('properties')
                ->orderBy('zone')
                ->orderBy('section')
                ->get();
        }
        
        // For field agents - get plans they are assigned to
        if ($user->isFieldAgent()) {
            // Get plan IDs from active assignments
            $planIds = \App\Models\PlanAgentAssignment::where('agent_id', $user->id)
                ->where('is_active', true)
                ->pluck('plan_id')
                ->toArray();
            
            \Log::info('getRegistrationPlansForUser - Field Agent', [
                'user_id' => $user->id,
                'plan_ids' => $planIds
            ]);
            
            if (empty($planIds)) {
                return collect();
            }
            
            // Get the plans - INCLUDE 'assigned' status (not just 'active')
            $plans = \App\Models\RegistrationPlan::whereIn('id', $planIds)
                ->whereNull('deleted_at')
                ->whereIn('status', ['assigned', 'in_progress', 'draft'])
                ->withCount('properties')
                ->orderBy('zone')
                ->orderBy('section')
                ->get();
            
            \Log::info('getRegistrationPlansForUser - Plans found', [
                'count' => $plans->count(),
                'plans' => $plans->map(fn($p) => ['id' => $p->id, 'zone' => $p->zone, 'status' => $p->status])->toArray()
            ]);
            
            return $plans;
        }
        
        // For other user types - return empty collection
        return collect();
    }

    /**
     * Update agent assignment progress
     */
    protected function updateAgentAssignmentProgress($agentId, $planId)
    {
        try {
            $assignment = PlanAgentAssignment::where('agent_id', $agentId)
                ->where('plan_id', $planId)
                ->where('is_active', true)
                ->first();

            if ($assignment) {
                $propertiesRegistered = \App\Models\Property::where('registration_plan_id', $planId)
                    ->where('registered_by', $agentId)
                    ->count();

                $assignment->update([
                    'properties_registered' => $propertiesRegistered,
                    'last_activity_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error updating agent assignment progress: ' . $e->getMessage());
        }
    }
}
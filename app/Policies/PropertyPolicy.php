<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PropertyPolicy
{
    public function view(User $user, Property $property)
    {
        // Admins can view any property
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }
        
        // Landlords can only view their own properties
        return $user->id === $property->landlord_id;
    }
    
    // Add other policy methods as needed...
}
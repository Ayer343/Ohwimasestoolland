<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\PropertyOwnershipTransfer;
use App\Models\User;

class PropertyOwnershipTransferPolicy
{
    /**
     * Determine whether the user can transfer ownership of a property.
     * This method is called via Gate::allows('transfer-ownership', $property)
     */
    public function transferOwnership(User $user, Property $property): bool
    {
        // Only landlords can transfer ownership
        if (!$user->isLandlord()) {
            return false;
        }
        
        // User must own the property
        if ($user->id !== $property->landlord_id) {
            return false;
        }
        
        // Check if there's a pending transfer
        $pendingTransfer = $property->currentOwnershipTransfer;
        if ($pendingTransfer && $pendingTransfer->status === PropertyOwnershipTransfer::STATUS_PENDING) {
            return false;
        }
        
        return true;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admins can view all transfers
        if ($user->isAdmin()) {
            return true;
        }
        
        // Landlords can view transfers involving them
        return $user->isLandlord();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PropertyOwnershipTransfer $transfer): bool
    {
        // Admins can view all transfers
        if ($user->isAdmin()) {
            return true;
        }
        
        // Current or new landlord can view
        if ($user->id === $transfer->current_landlord_id || 
            $user->id === $transfer->new_landlord_id) {
            return true;
        }
        
        // User who requested the transfer can view
        if ($user->id === $transfer->requested_by_id) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only landlords can create transfer requests
        return $user->isLandlord();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PropertyOwnershipTransfer $transfer): bool
    {
        // Only admins can update transfers
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PropertyOwnershipTransfer $transfer): bool
    {
        // Only super admins can delete transfers
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PropertyOwnershipTransfer $transfer): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PropertyOwnershipTransfer $transfer): bool
    {
        return $user->isSuperAdmin();
    }
    
    /**
     * Determine whether the user can approve the transfer.
     */
    public function approve(User $user, PropertyOwnershipTransfer $transfer): bool
    {
        // Only admins can approve
        if (!$user->isAdmin()) {
            return false;
        }
        
        // Check if transfer can be approved
        return $transfer->canBeApproved();
    }
    
    /**
     * Determine whether the user can reject the transfer.
     */
    public function reject(User $user, PropertyOwnershipTransfer $transfer): bool
    {
        // Only admins can reject
        if (!$user->isAdmin()) {
            return false;
        }
        
        // Check if transfer can be rejected
        return $transfer->canBeRejected();
    }
    
    /**
     * Determine whether the user can complete the transfer.
     */
    public function complete(User $user, PropertyOwnershipTransfer $transfer): bool
    {
        // Only admins can complete
        if (!$user->isAdmin()) {
            return false;
        }
        
        // Check if transfer can be completed
        return $transfer->canBeCompleted();
    }
    
    /**
     * Determine whether the user can cancel the transfer.
     */
    public function cancel(User $user, PropertyOwnershipTransfer $transfer): bool
    {
        // Only current landlord can cancel
        if ($user->id !== $transfer->current_landlord_id) {
            return false;
        }
        
        // Only pending requests can be cancelled
        return $transfer->status === PropertyOwnershipTransfer::STATUS_PENDING;
    }
    
    /**
     * Determine whether the user can download the transfer document.
     */
    public function downloadDocument(User $user, PropertyOwnershipTransfer $transfer): bool
    {
        // Anyone authorized to view can download
        return $this->view($user, $transfer);
    }
    
    /**
     * Determine whether the user can resubmit a rejected transfer.
     */
    public function resubmit(User $user, PropertyOwnershipTransfer $transfer): bool
    {
        // Only the current landlord can resubmit
        if ($user->id !== $transfer->current_landlord_id) {
            return false;
        }
        
        // Check if transfer can be resubmitted
        return $transfer->can_resubmit ?? false;
    }
    
    /**
     * Determine whether the user can manage webhooks for the transfer.
     */
    public function manageWebhooks(User $user, PropertyOwnershipTransfer $transfer): bool
    {
        // Only admins can manage webhooks
        return $user->isAdmin();
    }
    
    /**
     * Determine whether the user can view property ownership history.
     */
    public function viewPropertyHistory(User $user, Property $property): bool
    {
        // Admins can view all property history
        if ($user->isAdmin()) {
            return true;
        }
        
        // Landlords can view history of properties they own
        if ($user->isLandlord() && $user->id === $property->landlord_id) {
            return true;
        }
        
        return false;
    }
}
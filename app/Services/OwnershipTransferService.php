<?php

namespace App\Services;

use App\Models\PropertyOwnershipTransfer;
use App\Models\User;
use App\Models\Property;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OwnershipTransferService
{
    /**
     * Find or create new landlord user
     * IMPORTANT: This creates the user in PENDING status WITHOUT sending any emails
     * The invitation will be sent after admin approval via sendInvitationToNewOwner method
     */
    public function findOrCreateNewLandlord(array $data): User
    {
        // Normalize phone number first
        $normalizedPhone = $this->normalizePhoneNumber($data['phone']);
        
        // Check if user exists by phone (using normalized format)
        $user = User::where('phone', $normalizedPhone)->first();
        
        // Also try to find by email if provided
        if (!$user && !empty($data['email'])) {
            $user = User::where('email', $data['email'])->first();
        }
        
        if ($user) {
            // Update user details if needed
            $updateData = [];
            
            if (empty($user->email) && !empty($data['email'])) {
                $updateData['email'] = $data['email'];
            }
            
            if (empty($user->address) && !empty($data['address'])) {
                $updateData['address'] = $data['address'];
            }
            
            // Update name if different and not empty
            if (!empty($data['name']) && $user->name !== $data['name']) {
                $updateData['name'] = $data['name'];
            }
            
            if (!empty($updateData)) {
                $user->update($updateData);
                Log::info('Existing landlord updated', [
                    'user_id' => $user->id,
                    'phone' => $normalizedPhone,
                    'updates' => $updateData
                ]);
            }
            
            // If user exists but is not a landlord, we can still use them
            if (!$user->isLandlord()) {
                Log::info('Existing user is not a landlord, but will be used as new owner', [
                    'user_id' => $user->id,
                    'current_type' => $user->type,
                    'will_become_landlord' => true
                ]);
            }
            
            return $user;
        }

        // Generate a secure random password for the new user
        $randomPassword = $this->generateRandomPassword();
        
        // Generate a fallback email if not provided
        $email = !empty($data['email']) 
            ? $data['email'] 
            : $this->generateFallbackEmail($normalizedPhone, $data['name']);
        
        // Create new landlord user with all required fields
        $userData = [
            'name' => $data['name'],
            'phone' => $normalizedPhone,
            'email' => $email,
            'password' => Hash::make($randomPassword),
            'type' => User::TYPE_LANDLORD,
            'status' => User::STATUS_PENDING,
            'supervisor_level' => 0,
            'supervisor_score' => 0,
            'can_be_supervisor' => false,
        ];
        
        // Add address only if provided
        if (!empty($data['address'])) {
            $userData['address'] = $data['address'];
        }
        
        // Add metadata
        $metadata = [
            'created_via' => 'ownership_transfer',
            'initial_status' => 'pending',
            'temp_password' => $randomPassword,
            'created_at' => now()->toISOString(),
            'transfer_source' => 'ownership_transfer_request',
            'has_real_email' => !empty($data['email']),
            'fallback_email_generated' => empty($data['email'])
        ];
        
        $userData['metadata'] = json_encode($metadata);
        
        try {
            $user = User::create($userData);
            
            Log::info('New landlord created for ownership transfer (pending invitation)', [
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'has_real_email' => !empty($data['email']),
                'status' => 'pending'
            ]);
            
            return $user;
            
        } catch (\Exception $e) {
            Log::error('Failed to create new landlord user', [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Failed to create new landlord account: ' . $e->getMessage());
        }
    }

    /**
     * Generate a fallback email when none is provided
     */
    private function generateFallbackEmail(string $phone, string $name): string
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        
        if (empty($cleanName)) {
            $cleanName = 'user';
        }
        
        $phoneSuffix = substr($cleanPhone, -6);
        $domain = config('app.fallback_email_domain', 'temp.hilltop.com');
        $fallbackEmail = "{$cleanName}_{$phoneSuffix}@{$domain}";
        
        $counter = 1;
        $originalEmail = $fallbackEmail;
        while (User::where('email', $fallbackEmail)->exists()) {
            $fallbackEmail = str_replace("@{$domain}", "_{$counter}@{$domain}", $originalEmail);
            $counter++;
        }
        
        return $fallbackEmail;
    }

    /**
     * Generate a secure random password
     */
    private function generateRandomPassword(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';
        $max = strlen($chars) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        
        return $password;
    }
    
    /**
     * Normalize phone number to standard international format
     */
    private function normalizePhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        if (preg_match('/^0([0-9]{9})$/', $phone, $matches)) {
            $phone = '+233' . $matches[1];
        }
        
        if (!preg_match('/^\+/', $phone) && strlen($phone) > 0) {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }

    /**
     * Send invitation to new owner - CALLED AFTER ADMIN APPROVAL
     */
    public function sendInvitationToNewOwner(PropertyOwnershipTransfer $transfer): array
    {
        try {
            $newLandlord = $transfer->newLandlord;
            
            if (!$newLandlord) {
                Log::warning('Cannot send invitation: New landlord not found', [
                    'transfer_id' => $transfer->id,
                    'new_landlord_id' => $transfer->new_landlord_id
                ]);
                return ['success' => false, 'message' => 'New landlord not found'];
            }
            
            $isExistingLandlord = $transfer->metadata['is_existing_landlord'] ?? false;
            
            if ($isExistingLandlord) {
                Log::info('Skipping invitation for existing landlord', [
                    'transfer_id' => $transfer->id,
                    'user_id' => $newLandlord->id,
                    'user_name' => $newLandlord->name
                ]);
                return [
                    'success' => true, 
                    'message' => 'User already has an active account. No invitation needed.',
                    'skip_invitation' => true
                ];
            }
            
            if ($newLandlord->status === User::STATUS_ACTIVE) {
                Log::info('User already active, skipping invitation', [
                    'user_id' => $newLandlord->id,
                    'transfer_id' => $transfer->id
                ]);
                return ['success' => true, 'message' => 'User already active, no invitation needed'];
            }
            
            if (empty($newLandlord->invitation_token)) {
                $newLandlord->invitation_token = Str::random(64);
                $newLandlord->invitation_expires_at = now()->addDays(7);
                $newLandlord->save();
            }
            
            $invitationData = [
                'landlord' => $newLandlord,
                'property' => $transfer->property,
                'transfer' => $transfer,
                'current_owner' => $transfer->currentLandlord,
                'invitation_url' => route('invitations.accept', $newLandlord->invitation_token),
                'property_address' => ($transfer->property->street_name ?? '') . ', ' . ($transfer->property->zone ?? ''),
                'registration_pattern' => $transfer->property->registration_pattern ?? 'N/A',
                'transfer_date' => $transfer->transfer_date ? $transfer->transfer_date->format('F j, Y') : 'N/A'
            ];
            
            if ($newLandlord->email && class_exists(\App\Mail\PropertyOwnershipTransferInvitation::class)) {
                try {
                    \Mail::to($newLandlord->email)->send(
                        new \App\Mail\PropertyOwnershipTransferInvitation($invitationData)
                    );
                    
                    return [
                        'success' => true, 
                        'message' => 'Invitation email sent successfully',
                        'invitation_url' => $invitationData['invitation_url']
                    ];
                } catch (\Exception $e) {
                    Log::error('Failed to send invitation email', [
                        'error' => $e->getMessage(),
                        'user_id' => $newLandlord->id
                    ]);
                }
            }
            
            return [
                'success' => true, 
                'message' => 'Invitation token generated',
                'invitation_url' => $invitationData['invitation_url']
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to send invitation: ' . $e->getMessage(), [
                'transfer_id' => $transfer->id,
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'message' => 'Failed to send invitation: ' . $e->getMessage()];
        }
    }

    /**
     * Send notification for resubmitted transfer
     */
    public function notifyAdminsOfResubmittedTransfer(PropertyOwnershipTransfer $transfer, PropertyOwnershipTransfer $oldTransfer): void
    {
        try {
            $admins = User::whereIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])
                         ->where('status', User::STATUS_ACTIVE)
                         ->get();

            if ($admins->count() > 0) {
                if (class_exists(\App\Notifications\TransferResubmittedNotification::class)) {
                    Notification::send($admins, new \App\Notifications\TransferResubmittedNotification($transfer, $oldTransfer));
                    Log::info('Admins notified of resubmitted transfer', [
                        'new_transfer_id' => $transfer->id,
                        'old_transfer_id' => $oldTransfer->id,
                        'admin_count' => $admins->count()
                    ]);
                } else {
                    if (class_exists(\App\Notifications\OwnershipTransferRequested::class)) {
                        Notification::send($admins, new \App\Notifications\OwnershipTransferRequested($transfer));
                        Log::info('Admins notified via fallback notification', [
                            'new_transfer_id' => $transfer->id
                        ]);
                    }
                }
            }

            if (class_exists(ActivityLog::class)) {
                ActivityLog::create([
                    'user_id' => $transfer->requested_by_id,
                    'action' => 'ownership_transfer_resubmitted',
                    'description' => "Ownership transfer resubmitted for property: {$transfer->property->property_name} (from original transfer #{$oldTransfer->id})",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'metadata' => [
                        'property_id' => $transfer->property_id,
                        'new_transfer_id' => $transfer->id,
                        'old_transfer_id' => $oldTransfer->id,
                        'original_rejection_reason' => $oldTransfer->rejection_reason
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to notify admins of resubmitted transfer: ' . $e->getMessage());
        }
    }

    /**
     * Send confirmation to landlord that their transfer was resubmitted
     */
    public function notifyLandlordOfResubmission(PropertyOwnershipTransfer $transfer, PropertyOwnershipTransfer $oldTransfer): void
    {
        try {
            $landlord = $transfer->currentLandlord;
            
            if ($landlord && $landlord->status === User::STATUS_ACTIVE) {
                if (class_exists(\App\Notifications\TransferResubmittedConfirmation::class)) {
                    Notification::send($landlord, new \App\Notifications\TransferResubmittedConfirmation($transfer, $oldTransfer));
                    Log::info('Landlord notified of resubmission confirmation', [
                        'new_transfer_id' => $transfer->id,
                        'landlord_id' => $landlord->id
                    ]);
                } else {
                    if (class_exists(\App\Notifications\TransferStatusNotification::class)) {
                        $landlord->notify(new \App\Notifications\TransferStatusNotification($transfer, 'resubmitted'));
                        Log::info('Landlord notified via fallback notification');
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to notify landlord of resubmission: ' . $e->getMessage());
        }
    }

    /**
     * Validate if a transfer can be resubmitted
     */
    public function canResubmitTransfer(PropertyOwnershipTransfer $transfer, User $user): array
    {
        $reasons = [];
        
        if ($user->id !== $transfer->current_landlord_id) {
            $reasons[] = 'Only the original sender can resubmit this transfer.';
        }
        
        if ($transfer->status !== PropertyOwnershipTransfer::STATUS_REJECTED) {
            $reasons[] = 'Only rejected transfers can be resubmitted.';
        }
        
        if ($transfer->can_resubmit_after && $transfer->can_resubmit_after->isFuture()) {
            $reasons[] = 'Resubmission is not allowed until ' . $transfer->can_resubmit_after->format('F j, Y');
        }
        
        if (isset($transfer->metadata['resubmitted_to'])) {
            $reasons[] = 'This transfer has already been resubmitted.';
        }
        
        if (!$transfer->property) {
            $reasons[] = 'The associated property no longer exists.';
        } elseif ($transfer->property->landlord_id !== $user->id) {
            $reasons[] = 'You no longer own this property.';
        }
        
        $existingPending = PropertyOwnershipTransfer::where('property_id', $transfer->property_id)
            ->where('status', PropertyOwnershipTransfer::STATUS_PENDING)
            ->where('id', '!=', $transfer->id)
            ->exists();
            
        if ($existingPending) {
            $reasons[] = 'There is already a pending transfer request for this property.';
        }
        
        return [
            'can_resubmit' => empty($reasons),
            'reasons' => $reasons
        ];
    }

    /**
     * Prepare resubmission data from rejected transfer
     */
    public function prepareResubmissionData(PropertyOwnershipTransfer $oldTransfer): array
    {
        return [
            'property_id' => $oldTransfer->property_id,
            'current_landlord_id' => $oldTransfer->current_landlord_id,
            'new_landlord_id' => $oldTransfer->new_landlord_id,
            'transfer_date' => $oldTransfer->transfer_date,
            'sale_amount' => $oldTransfer->sale_amount,
            'document_type' => $oldTransfer->document_type,
            'document_reference' => $oldTransfer->document_reference,
            'document_url' => $oldTransfer->document_url,
            'new_owner_name' => $oldTransfer->new_owner_name,
            'new_owner_phone' => $oldTransfer->new_owner_phone,
            'new_owner_email' => $oldTransfer->new_owner_email,
            'new_owner_address' => $oldTransfer->new_owner_address,
            'reason_for_transfer' => $oldTransfer->reason_for_transfer,
            'notes' => $oldTransfer->notes,
            'is_existing_landlord' => $oldTransfer->metadata['is_existing_landlord'] ?? false,
            'existing_landlord_id' => $oldTransfer->metadata['existing_landlord_id'] ?? null,
            'original_rejection_reason' => $oldTransfer->rejection_reason,
            'resubmission_attempt' => ($oldTransfer->metadata['resubmission_attempt'] ?? 0) + 1
        ];
    }

    /**
     * Notify admins of new transfer request
     */
    public function notifyAdminsOfTransferRequest(PropertyOwnershipTransfer $transfer): void
    {
        try {
            $admins = User::whereIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])
                         ->where('status', User::STATUS_ACTIVE)
                         ->get();

            if ($admins->count() > 0) {
                if (class_exists(\App\Notifications\OwnershipTransferRequested::class)) {
                    Notification::send($admins, new \App\Notifications\OwnershipTransferRequested($transfer));
                    Log::info('Admins notified of transfer request', [
                        'transfer_id' => $transfer->id,
                        'admin_count' => $admins->count()
                    ]);
                }
            }

            if (class_exists(ActivityLog::class)) {
                ActivityLog::create([
                    'user_id' => $transfer->requested_by_id,
                    'action' => 'ownership_transfer_requested',
                    'description' => "Ownership transfer requested for property: {$transfer->property->property_name}",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'metadata' => [
                        'property_id' => $transfer->property_id,
                        'transfer_id' => $transfer->id,
                        'current_landlord' => $transfer->currentLandlord->name ?? 'Unknown',
                        'new_landlord' => $transfer->new_owner_name
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to notify admins of transfer request: ' . $e->getMessage());
        }
    }

    /**
     * Notify admins of bulk transfer request
     */
    public function notifyAdminsOfBulkTransfer(array $transfers, int $failedCount): void
    {
        try {
            $admins = User::whereIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])
                         ->where('status', User::STATUS_ACTIVE)
                         ->get();

            if ($admins->count() > 0 && count($transfers) > 0) {
                if (class_exists(\App\Notifications\BulkTransferRequestNotification::class)) {
                    Notification::send($admins, new \App\Notifications\BulkTransferRequestNotification($transfers, $failedCount));
                    Log::info('Admins notified of bulk transfer', [
                        'transfer_count' => count($transfers),
                        'failed_count' => $failedCount,
                        'admin_count' => $admins->count()
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to notify admins of bulk transfer: ' . $e->getMessage());
        }
    }

    /**
     * Notify current landlord of approval
     */
    public function notifyCurrentLandlordOfApproval(PropertyOwnershipTransfer $transfer): void
    {
        try {
            $currentLandlord = $transfer->currentLandlord;
            
            if ($currentLandlord && $currentLandlord->status === User::STATUS_ACTIVE) {
                if (class_exists(\App\Notifications\OwnershipTransferApproved::class)) {
                    Notification::send($currentLandlord, new \App\Notifications\OwnershipTransferApproved($transfer, 'current_landlord'));
                    Log::info('Current landlord notified of approval', [
                        'transfer_id' => $transfer->id,
                        'landlord_id' => $currentLandlord->id
                    ]);
                }
            }

            if (class_exists(ActivityLog::class)) {
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'ownership_transfer_approved',
                    'description' => "Ownership transfer approved for property: {$transfer->property->property_name}",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'metadata' => [
                        'property_id' => $transfer->property_id,
                        'transfer_id' => $transfer->id,
                        'approved_by' => auth()->user()->name ?? 'System'
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to notify current landlord of approval: ' . $e->getMessage());
        }
    }

    /**
     * Notify current landlord of rejection
     */
    public function notifyCurrentLandlordOfRejection(PropertyOwnershipTransfer $transfer): void
    {
        try {
            $currentLandlord = $transfer->currentLandlord;
            
            if ($currentLandlord && $currentLandlord->status === User::STATUS_ACTIVE) {
                if (class_exists(\App\Notifications\OwnershipTransferRejected::class)) {
                    Notification::send($currentLandlord, new \App\Notifications\OwnershipTransferRejected($transfer));
                    Log::info('Current landlord notified of rejection', [
                        'transfer_id' => $transfer->id,
                        'landlord_id' => $currentLandlord->id
                    ]);
                }
            }

            if (class_exists(ActivityLog::class)) {
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'ownership_transfer_rejected',
                    'description' => "Ownership transfer rejected for property: {$transfer->property->property_name}",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'metadata' => [
                        'property_id' => $transfer->property_id,
                        'transfer_id' => $transfer->id,
                        'rejected_by' => auth()->user()->name ?? 'System',
                        'reason' => $transfer->rejection_reason
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to notify current landlord of rejection: ' . $e->getMessage());
        }
    }

    /**
     * Notify both parties of completion
     */
    public function notifyPartiesOfCompletion(PropertyOwnershipTransfer $transfer, ?string $certificatePath = null): void
    {
        try {
            $currentLandlord = $transfer->currentLandlord;
            $newLandlord = $transfer->newLandlord;

            if ($currentLandlord && $currentLandlord->status === User::STATUS_ACTIVE) {
                if (class_exists(\App\Notifications\OwnershipTransferCompleted::class)) {
                    Notification::send($currentLandlord, new \App\Notifications\OwnershipTransferCompleted($transfer, 'previous_owner', $certificatePath));
                    Log::info('Previous owner notified of completion', [
                        'transfer_id' => $transfer->id,
                        'landlord_id' => $currentLandlord->id
                    ]);
                }
            }

            if ($newLandlord && $newLandlord->status === User::STATUS_ACTIVE) {
                if (class_exists(\App\Notifications\OwnershipTransferCompleted::class)) {
                    Notification::send($newLandlord, new \App\Notifications\OwnershipTransferCompleted($transfer, 'new_owner', $certificatePath));
                    Log::info('New owner notified of completion', [
                        'transfer_id' => $transfer->id,
                        'landlord_id' => $newLandlord->id
                    ]);
                }
            }

            if (class_exists(ActivityLog::class)) {
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'ownership_transfer_completed',
                    'description' => "Ownership transfer completed for property: {$transfer->property->property_name}",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'metadata' => [
                        'property_id' => $transfer->property_id,
                        'transfer_id' => $transfer->id,
                        'previous_owner' => $currentLandlord->name ?? 'Unknown',
                        'new_owner' => $newLandlord->name ?? $transfer->new_owner_name,
                        'completed_by' => auth()->user()->name ?? 'System',
                        'certificate_generated' => !empty($certificatePath)
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to notify parties of completion: ' . $e->getMessage());
        }
    }

    /**
     * Update property ownership history
     */
    public function updatePropertyOwnershipHistory(Property $property, PropertyOwnershipTransfer $transfer): void
    {
        try {
            if (!\Schema::hasTable('property_ownership_history')) {
                \Log::warning('property_ownership_history table does not exist');
                return;
            }
            
            $data = [
                'property_id' => $property->id,
                'previous_landlord_id' => $transfer->current_landlord_id,
                'previous_owner_name' => $transfer->currentLandlord->name ?? null,
                'new_landlord_id' => $transfer->new_landlord_id,
                'new_owner_name' => $transfer->newLandlord->name ?? $transfer->new_owner_name,
                'transfer_id' => $transfer->id,
                'transfer_date' => $transfer->transfer_date,
                'sale_amount' => $transfer->sale_amount,
                'document_type' => $transfer->document_type,
                'document_reference' => $transfer->document_reference,
                'admin_name' => auth()->user()->name ?? null,
                'metadata' => json_encode([
                    'document_url' => $transfer->document_url,
                    'approved_by' => $transfer->admin_approved_by_id,
                    'completed_by' => $transfer->completed_by_id,
                    'completed_at' => $transfer->completed_at,
                    'current_owner_phone' => $transfer->currentLandlord->phone ?? null,
                    'current_owner_email' => $transfer->currentLandlord->email ?? null,
                    'new_owner_phone' => $transfer->new_owner_phone ?? null,
                    'new_owner_email' => $transfer->new_owner_email ?? null,
                    'reason_for_transfer' => $transfer->reason_for_transfer,
                    'notes' => $transfer->notes
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            DB::table('property_ownership_history')->insert($data);

            \Log::info('Property ownership history updated', [
                'property_id' => $property->id,
                'transfer_id' => $transfer->id
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to update property ownership history: ' . $e->getMessage());
        }
    }

    /**
     * Validate ownership transfer request
     */
    public function validateTransferRequest(Property $property, User $currentLandlord): array
    {
        $errors = [];

        if (method_exists($property, 'tenants') && $property->tenants()->exists()) {
            $errors[] = 'Property has active tenants. Please remove tenants before transferring ownership.';
        }

        if (method_exists($property, 'payments')) {
            $pendingPayments = $property->payments()
                ->whereIn('status', ['pending', 'partially_paid'])
                ->exists();
                
            if ($pendingPayments) {
                $errors[] = 'Property has pending payments. Please clear all payments before transferring ownership.';
            }
        }

        $pendingTransfer = $property->currentOwnershipTransfer;
        if ($pendingTransfer) {
            $errors[] = 'There is already a pending ownership transfer request for this property.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get transfer statistics for dashboard
     */
    public function getTransferStatistics(): array
    {
        $total = PropertyOwnershipTransfer::count();
        $pending = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_PENDING)->count();
        $approved = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_APPROVED)->count();
        $completed = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)->count();
        $rejected = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_REJECTED)->count();
        $cancelled = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_CANCELLED)->count();

        $today = PropertyOwnershipTransfer::whereDate('created_at', today())->count();
        $thisWeek = PropertyOwnershipTransfer::whereBetween('created_at', [
            now()->startOfWeek(), now()->endOfWeek()
        ])->count();

        $thisMonth = PropertyOwnershipTransfer::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $totalValue = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
            ->sum('sale_amount');

        // Count resubmittable transfers
        $resubmittable = PropertyOwnershipTransfer::where('status', PropertyOwnershipTransfer::STATUS_REJECTED)
            ->where(function($q) {
                $q->whereNull('can_resubmit_after')
                  ->orWhere('can_resubmit_after', '<=', now());
            })
            ->whereNull('metadata->resubmitted_to')
            ->count();

        return [
            'total_transfers' => $total,
            'pending_transfers' => $pending,
            'approved_transfers' => $approved,
            'completed_transfers' => $completed,
            'rejected_transfers' => $rejected,
            'cancelled_transfers' => $cancelled,
            'resubmittable_transfers' => $resubmittable,
            'total_value' => $totalValue,
            'today' => $today,
            'this_week' => $thisWeek,
            'this_month' => $thisMonth,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'approval_rate' => ($pending + $approved + $rejected) > 0 ? 
                round(($approved / ($pending + $approved + $rejected)) * 100, 2) : 0,
        ];
    }
}
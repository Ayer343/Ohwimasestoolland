<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserCreationService
{
    /**
     * Create a user with a specific type.
     */
    public function createUserWithType(array $data, string $role): User
    {
        $validRoles = array_values(User::getRoleMapping());

        if (!in_array($role, $validRoles)) {
            throw new \InvalidArgumentException("Invalid role provided.");
        }

        $data['type'] = array_search($role, User::getRoleMapping());

        if (auth()->check()) {
            $data['created_by'] = auth()->id();
        }

        $user = new User();
        $user->fill($data);
        $user->save();

        return $user;
    }

    /**
     * Create a field agent with invitation
     */
    public function createFieldAgent(array $data): User
    {
        $data['type'] = User::TYPE_FIELD_AGENT;
        $data['status'] = User::STATUS_PENDING;
        
        if (empty($data['username'])) {
            $data['username'] = 'agent_' . Str::random(8);
        }
        
        if (empty($data['email']) && !empty($data['phone'])) {
            $data['email'] = $data['username'] . '@fieldagent.propertyreg.com';
        }
        
        $data['password'] = Hash::make(Str::random(32));
        
        $data['metadata'] = array_merge($data['metadata'] ?? [], [
            'created_via' => 'invitation',
            'agent_type' => 'field_agent',
            'initial_invitation_sent' => false
        ]);

        $user = new User();
        $user->fill($data);
        $user->save();

        return $user;
    }

    /**
     * Create user with invitation
     */
    public function createUserWithInvitation(array $data, $sendInvitation = false): User
    {
        $data['status'] = User::STATUS_PENDING;
        
        $user = new User();
        $user->fill($data);
        $user->password = Hash::make(Str::random(32));
        $user->save();

        if ($sendInvitation) {
            $user->createInvitation();
        }

        return $user;
    }

    /**
     * Get users needing invitation reminders
     */
    public function getUsersNeedingInvitationReminders($daysBeforeExpiry = 2)
    {
        return User::withValidInvitations()
                    ->whereHas('invitations', function($q) use ($daysBeforeExpiry) {
                        $q->where('expires_at', '<=', now()->addDays($daysBeforeExpiry));
                    })
                    ->get();
    }
}
<?php
// app/Services/UserDisplayService.php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserDisplayService
{
    /**
     * Prepare user for display with computed properties
     */
    public function prepareForDisplay(User $user): User
    {
        $user->display_type = $this->getTypeDisplayInfo($user->type);
        $user->display_status = $this->getStatusDisplayInfo($user->status);
        $user->display_phone = $this->getPhoneDisplayInfo($user->phone, $user->phone_verified_at);
        $user->display_photo = $this->getPhotoDisplayInfo($user);
        $user->is_current_user = $user->id === auth()->id();
        $user->has_photo = $this->userHasPhoto($user);
        $user->initials = $this->getUserInitials($user->name, $user->email);
        $user->local_phone = $this->getLocalPhone($user->phone);
        $user->all_roles = $user->getAllRolesAttribute();
        $user->role_badges_html = $user->getRoleBadgesHtml();
        $user->primary_role_name = $user->getPrimaryRoleNameAttribute();
        $user->is_multi_role_user = $user->roles()->count() > 1;
        $user->property_count = $user->ownedProperties()->count();
        $user->is_property_owner = $user->property_count > 0;
        $user->creator_name = $user->creator ? $user->creator->name : 'System';
        $user->avatar_url = $this->getAvatarUrl($user);
        
        return $user;
    }
    
    /**
     * Get type display information
     */
    public function getTypeDisplayInfo(int $type): array
    {
        $config = [
            User::TYPE_SUPER_ADMIN => [
                'label' => 'Super Admin',
                'class' => 'bg-purple-100 text-purple-800',
                'icon' => 'user-shield',
            ],
            User::TYPE_ADMIN => [
                'label' => 'Admin',
                'class' => 'bg-blue-100 text-blue-800',
                'icon' => 'user-shield',
            ],
            User::TYPE_LANDLORD => [
                'label' => 'Landlord',
                'class' => 'bg-green-100 text-green-800',
                'icon' => 'home',
            ],
            User::TYPE_TENANT => [
                'label' => 'Tenant',
                'class' => 'bg-yellow-100 text-yellow-800',
                'icon' => 'user-friends',
            ],
            User::TYPE_FIELD_AGENT => [
                'label' => 'Field Agent',
                'class' => 'bg-cyan-100 text-cyan-800',
                'icon' => 'user-check',
            ],
            User::TYPE_SECURITY_PERSONNEL => [
                'label' => 'Security Personnel',
                'class' => 'bg-orange-100 text-orange-800',
                'icon' => 'shield-alt',
            ],
        ];
        
        return $config[$type] ?? [
            'label' => 'Unknown',
            'class' => 'bg-gray-100 text-gray-800',
            'icon' => 'user',
        ];
    }
    
    /**
     * Get status display information
     */
    public function getStatusDisplayInfo(string $status): array
    {
        $config = [
            User::STATUS_PENDING => [
                'label' => 'Pending',
                'class' => 'bg-yellow-100 text-yellow-800',
                'icon' => 'clock',
            ],
            User::STATUS_ACTIVE => [
                'label' => 'Active',
                'class' => 'bg-green-100 text-green-800',
                'icon' => 'check-circle',
            ],
            User::STATUS_SUSPENDED => [
                'label' => 'Suspended',
                'class' => 'bg-red-100 text-red-800',
                'icon' => 'ban',
            ],
            User::STATUS_INACTIVE => [
                'label' => 'Inactive',
                'class' => 'bg-gray-100 text-gray-800',
                'icon' => 'minus-circle',
            ],
            User::STATUS_VERIFICATION_REQUIRED => [
                'label' => 'Verification Required',
                'class' => 'bg-blue-100 text-blue-800',
                'icon' => 'shield-alt',
            ],
            User::STATUS_ARCHIVED => [
                'label' => 'Archived',
                'class' => 'bg-gray-100 text-gray-800',
                'icon' => 'archive',
            ],
        ];
        
        return $config[$status] ?? [
            'label' => 'Unknown',
            'class' => 'bg-gray-100 text-gray-800',
            'icon' => 'question-circle',
        ];
    }
    
    /**
     * Get phone display information
     */
    public function getPhoneDisplayInfo(?string $phone, ?string $verifiedAt): array
    {
        return [
            'original' => $phone,
            'local_format' => $this->getLocalPhone($phone),
            'is_verified' => !is_null($verifiedAt),
            'verified_at' => $verifiedAt,
        ];
    }
    
    /**
     * Get local phone format
     */
    public function getLocalPhone(?string $phone): string
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
     * Get photo display information
     */
    public function getPhotoDisplayInfo(User $user): array
    {
        $hasPhoto = $this->userHasPhoto($user);
        
        return [
            'has_photo' => $hasPhoto,
            'photo_url' => $hasPhoto ? Storage::disk('public')->url('users/photos/' . $user->photo) : null,
            'file_name' => $user->photo,
        ];
    }
    
    /**
     * Check if user has photo
     */
    public function userHasPhoto(User $user): bool
    {
        return !empty($user->photo) && Storage::disk('public')->exists('users/photos/' . $user->photo);
    }
    
    /**
     * Get avatar URL
     */
    public function getAvatarUrl(User $user): string
    {
        if ($this->userHasPhoto($user)) {
            return Storage::disk('public')->url('users/photos/' . $user->photo);
        }
        
        return config('app.placeholder_avatar', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSIjRkZGIi8+CjxjaXJjbGUgY3g9IjIwIiBjeT0iMTQiIHI9IjYiIGZpbGw9IiNlZWVlZWUiLz4KPHBhdGggZD0iTTIwIDI3QzI0Ljk3MDYgMjcgMjkgMzAuNTgyMyAyOSAzNUgxMUMxMSAzMC41ODIzIDE1LjAyOTQgMjcgMjAgMjdaIiBmaWxsPSIjZWVlZWVlIi8+Cjwvc3ZnPgo=');
    }
    
    /**
     * Get user initials for avatar
     */
    public function getUserInitials(string $name, ?string $email): string
    {
        $names = explode(' ', trim($name));
        
        if (count($names) >= 2) {
            return strtoupper(substr($names[0], 0, 1) . substr($names[count($names) - 1], 0, 1));
        }
        
        if (count($names) === 1) {
            return strtoupper(substr($names[0], 0, 2));
        }
        
        return strtoupper(substr($email ?? 'US', 0, 2));
    }
    
    /**
     * Get field agent display info
     */
    public function getFieldAgentDisplayInfo(User $user): array
    {
        if ($user->type !== User::TYPE_FIELD_AGENT) {
            return ['is_field_agent' => false];
        }
        
        return [
            'is_field_agent' => true,
            'has_accepted_invitation' => !is_null($user->invitation_accepted_at),
            'is_invitation_pending' => $user->status === User::STATUS_PENDING && is_null($user->invitation_accepted_at),
            'is_phone_verified' => !is_null($user->phone_verified_at),
        ];
    }
}
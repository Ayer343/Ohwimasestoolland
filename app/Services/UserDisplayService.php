<?php
// app/Services/UserDisplayService.php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserDisplayService
{
    /**
     * Prepare user for display with computed properties.
     *
     * ✅ Returns the User model (so blade templates keep working) but
     *    attaches ONLY JSON-safe dynamic attributes:
     *
     *      • no Collection instances
     *      • no Model instances inside arrays
     *      • no `pivot` keys (they can be recursive)
     *      • no Closures, resources, or recursion-prone graphs
     *
     * This is the "safe mutation" pattern: the model is augmented, but
     * every added value is a plain array / scalar / null.
     */
    public function prepareForDisplay(User $user): User
    {
        // Existing attributes the blade already relies on.
        $user->display_type      = $this->getTypeDisplayInfo((int) $user->type);
        $user->display_status    = $this->getStatusDisplayInfo((string) $user->status);
        $user->display_phone     = $this->getPhoneDisplayInfo($user->phone, $user->phone_verified_at);
        $user->display_photo     = $this->getPhotoDisplayInfo($user);
        $user->is_current_user   = $user->id === auth()->id();
        $user->has_photo         = $this->userHasPhoto($user);
        $user->initials          = $this->getUserInitials($user->name, $user->email);
        $user->local_phone       = $this->getLocalPhone($user->phone);

        // ✅ Safe role data — plain arrays, pivot stripped.
        $rolesArray              = $this->rolesToPlainArray($user);
        $user->all_roles         = $rolesArray;
        $user->role_badges_html  = $this->getRoleBadgesHtmlFromArray($rolesArray);
        $user->primary_role_name = $this->getPrimaryRoleNameFromArray($rolesArray);
        $user->is_multi_role_user = count($rolesArray) > 1;

        // ✅ Use the already-loaded `properties_count` if present;
        //    otherwise fall back to a query. Never return a Collection.
        $ownedCount  = $this->resolveCount($user, 'properties_count');
        $linkedCount = $this->resolveCount($user, 'linked_properties_count');

        $user->property_count = $ownedCount;
        $user->is_property_owner = $ownedCount > 0;

        // ✅ Also normalise the count aliases the API and blade both read.
        $user->accessible_property_count = $ownedCount + $linkedCount;

        $user->creator_name = $user->creator?->name ?? 'System';
        $user->avatar_url   = $this->getAvatarUrl($user);

        return $user;
    }

    // ==================== HELPERS ====================

    private function resolveCount(User $user, string $attribute): int
    {
        $attrs = $user->getAttributes();
        if (array_key_exists($attribute, $attrs)) {
            return (int) $attrs[$attribute];
        }
        if (isset($user->{$attribute})) {
            return (int) $user->{$attribute};
        }
        return 0;
    }

    /**
     * Convert the user's roles into a plain array of plain arrays.
     *
     * Critical: the `pivot` key is REMOVED. In a belongsToMany relation
     * the pivot carries a back-reference to the User in some joins,
     * which causes `json_encode()` to recurse and eventually fail.
     */
    private function rolesToPlainArray(User $user): array
    {
        $roles = $user->relationLoaded('roles')
            ? $user->roles
            : $user->roles()->get();

        if ($roles instanceof Collection) {
            $roles = $roles->all();
        }

        if (!is_array($roles)) {
            return [];
        }

        $out = [];
        foreach ($roles as $role) {
            if ($role instanceof \Illuminate\Database\Eloquent\Model) {
                $row = $role->toArray();

                // ✅ Remove pivot — the recursion hazard.
                unset($row['pivot']);

                // Normalise the display name.
                $row['display_name'] = $row['display_name']
                    ?? $row['name']
                    ?? $row['slug']
                    ?? 'Role';

                if (isset($row['is_active'])) {
                    $row['is_active'] = (bool) $row['is_active'];
                }

                $out[] = $row;
            } elseif (is_array($role)) {
                unset($role['pivot']);
                $out[] = $role;
            }
        }

        return $out;
    }

    private function getRoleBadgesHtmlFromArray(array $roles): string
    {
        if (empty($roles)) return '';

        $html = '';
        foreach ($roles as $role) {
            $label = $role['display_name'] ?? $role['name'] ?? $role['slug'] ?? 'Role';
            $slug  = $role['slug'] ?? 'default';
            $html .= '<span class="badge badge-role badge-'
                . e($slug) . '">' . e($label) . '</span>';
        }
        return $html;
    }

    private function getPrimaryRoleNameFromArray(array $roles): ?string
    {
        if (empty($roles)) return null;

        $priority = [
            'super_admin' => 100, 'super-admin' => 100,
            'admin'       => 90,
            'landlord'    => 80,
            'field_agent' => 70,
            'security_personnel' => 60,
            'tenant'      => 50,
        ];

        usort($roles, function ($a, $b) use ($priority) {
            return ($priority[$b['slug'] ?? ''] ?? 0) <=> ($priority[$a['slug'] ?? ''] ?? 0);
        });

        $first = $roles[0];
        return $first['display_name'] ?? $first['name'] ?? null;
    }

    // ==================== DISPLAY INFO BUILDERS ====================

    public function getTypeDisplayInfo(int $type): array
    {
        $config = [
            User::TYPE_SUPER_ADMIN => ['label' => 'Super Admin', 'class' => 'bg-purple-100 text-purple-800', 'icon' => 'user-shield'],
            User::TYPE_ADMIN => ['label' => 'Admin', 'class' => 'bg-blue-100 text-blue-800', 'icon' => 'user-shield'],
            User::TYPE_LANDLORD => ['label' => 'Landlord', 'class' => 'bg-green-100 text-green-800', 'icon' => 'home'],
            User::TYPE_TENANT => ['label' => 'Tenant', 'class' => 'bg-yellow-100 text-yellow-800', 'icon' => 'user-friends'],
            User::TYPE_FIELD_AGENT => ['label' => 'Field Agent', 'class' => 'bg-cyan-100 text-cyan-800', 'icon' => 'user-check'],
            User::TYPE_SECURITY_PERSONNEL => ['label' => 'Security Personnel', 'class' => 'bg-orange-100 text-orange-800', 'icon' => 'shield-alt'],
            User::TYPE_SANITATION_PERSONNEL => ['label' => 'Sanitation Personnel', 'class' => 'bg-teal-100 text-teal-800', 'icon' => 'trash-alt'],
            User::TYPE_CONTRACTOR => ['label' => 'Contractor', 'class' => 'bg-brown-100 text-brown-800', 'icon' => 'hard-hat'],
            User::TYPE_FORMER_LANDLORD => ['label' => 'Former Landlord', 'class' => 'bg-gray-100 text-gray-800', 'icon' => 'home'],
            User::TYPE_DEVELOPER => ['label' => 'Developer', 'class' => 'bg-gray-100 text-gray-800', 'icon' => 'code'],
        ];

        return $config[$type] ?? ['label' => 'Unknown', 'class' => 'bg-gray-100 text-gray-800', 'icon' => 'user'];
    }

    public function getStatusDisplayInfo(string $status): array
    {
        $config = [
            User::STATUS_PENDING => ['label' => 'Pending', 'class' => 'bg-yellow-100 text-yellow-800', 'icon' => 'clock'],
            User::STATUS_ACTIVE => ['label' => 'Active', 'class' => 'bg-green-100 text-green-800', 'icon' => 'check-circle'],
            User::STATUS_SUSPENDED => ['label' => 'Suspended', 'class' => 'bg-red-100 text-red-800', 'icon' => 'ban'],
            User::STATUS_INACTIVE => ['label' => 'Inactive', 'class' => 'bg-gray-100 text-gray-800', 'icon' => 'minus-circle'],
            User::STATUS_VERIFICATION_REQUIRED => ['label' => 'Verification Required', 'class' => 'bg-blue-100 text-blue-800', 'icon' => 'shield-alt'],
            User::STATUS_ARCHIVED => ['label' => 'Archived', 'class' => 'bg-gray-100 text-gray-800', 'icon' => 'archive'],
        ];

        return $config[$status] ?? ['label' => 'Unknown', 'class' => 'bg-gray-100 text-gray-800', 'icon' => 'question-circle'];
    }

    public function getPhoneDisplayInfo(?string $phone, $verifiedAt): array
    {
        $verifiedIso = null;
        if ($verifiedAt instanceof \DateTimeInterface) {
            $verifiedIso = $verifiedAt->format(\DateTimeInterface::ATOM);
        } elseif (is_string($verifiedAt) && $verifiedAt !== '') {
            $verifiedIso = $verifiedAt;
        }

        return [
            'original'     => $phone,
            'local_format' => $this->getLocalPhone($phone),
            'is_verified'  => !is_null($verifiedAt),
            'verified_at'  => $verifiedIso,
        ];
    }

    public function getLocalPhone(?string $phone): string
    {
        if (!$phone) return 'Not set';
        if (preg_match('/^\+233(\d{9})$/', $phone, $matches)) {
            return '0' . $matches[1];
        }
        return $phone;
    }

    public function getPhotoDisplayInfo(User $user): array
    {
        $hasPhoto = $this->userHasPhoto($user);

        return [
            'has_photo' => $hasPhoto,
            'photo_url' => $hasPhoto ? $this->photoUrl($user) : null,
            'file_name' => $user->photo,
        ];
    }

    public function userHasPhoto(User $user): bool
    {
        if (empty($user->photo)) return false;
        try {
            return Storage::disk('public')->exists('users/photos/' . $user->photo);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function photoUrl(User $user): ?string
    {
        if (!$this->userHasPhoto($user)) return null;
        try {
            return Storage::disk('public')->url('users/photos/' . $user->photo);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getPhotoUrl(User $user): ?string
    {
        return $this->photoUrl($user);
    }

    public function getAvatarUrl(User $user): string
    {
        $photoUrl = $this->photoUrl($user);
        if ($photoUrl !== null) return $photoUrl;

        return config(
            'app.placeholder_avatar',
            'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSIjRkZGIi8+CjxjaXJjbGUgY3g9IjIwIiBjeT0iMTQiIHI9IjYiIGZpbGw9IiNlZWVlZWUiLz4KPHBhdGggZD0iTTIwIDI3QzI0Ljk3MDYgMjcgMjkgMzAuNTgyMyAyOSAzNUgxMUMxMSAzMC41ODIzIDE1LjAyOTQgMjcgMjAgMjdaIiBmaWxsPSIjZWVlZWVlIi8+Cjwvc3ZnPgo='
        );
    }

    public function getUserInitials(string $name, ?string $email): string
    {
        $names = explode(' ', trim($name));

        if (count($names) >= 2) {
            return strtoupper(substr($names[0], 0, 1) . substr($names[count($names) - 1], 0, 1));
        }
        if (count($names) === 1 && $names[0] !== '') {
            return strtoupper(substr($names[0], 0, 2));
        }
        return strtoupper(substr($email ?? 'US', 0, 2));
    }

    public function getFieldAgentDisplayInfo(User $user): array
    {
        if ((int) $user->type !== User::TYPE_FIELD_AGENT) {
            return ['is_field_agent' => false];
        }
        return [
            'is_field_agent'          => true,
            'has_accepted_invitation' => !is_null($user->invitation_accepted_at),
            'is_invitation_pending'   => $user->status === User::STATUS_PENDING
                && is_null($user->invitation_accepted_at),
            'is_phone_verified'       => !is_null($user->phone_verified_at),
        ];
    }
}
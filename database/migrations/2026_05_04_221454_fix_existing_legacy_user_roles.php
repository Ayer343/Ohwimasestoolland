<?php

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        // Role mapping for all legacy types
        $roleMapping = [
            User::TYPE_SUPER_ADMIN => 'super-admin',
            User::TYPE_ADMIN => 'admin',
            User::TYPE_LANDLORD => 'landlord',
            User::TYPE_TENANT => 'tenant',
            User::TYPE_FIELD_AGENT => 'field-agent',
            User::TYPE_SECURITY_PERSONNEL => 'security-personnel',
        ];
        
        foreach ($roleMapping as $type => $roleSlug) {
            $role = Role::where('slug', $roleSlug)->first();
            if (!$role) continue;
            
            // Get users with this legacy type who don't have the role
            $users = User::where('type', $type)
                ->whereDoesntHave('roles', function($q) use ($role) {
                    $q->where('role_id', $role->id);
                })
                ->get();
            
            foreach ($users as $user) {
                $user->roles()->attach($role->id, [
                    'assigned_by' => 1,
                    'assigned_at' => now(),
                    'is_active' => true,
                    'status' => 'active',
                    'assignment_reason' => 'Migrated from legacy user type',
                    'notes' => 'Auto-migration to role-based system'
                ]);
                
                echo "Assigned {$roleSlug} role to {$user->name} (ID: {$user->id})\n";
            }
        }
        
        // Special: Users who have landlord role but are not legacy landlords
        $landlordRole = Role::where('slug', 'landlord')->first();
        if ($landlordRole) {
            $landlordRoleUsers = User::whereHas('roles', function($q) use ($landlordRole) {
                $q->where('role_id', $landlordRole->id);
            })->where('type', '!=', User::TYPE_LANDLORD)->get();
            
            foreach ($landlordRoleUsers as $user) {
                // These are multi-role users - mark them appropriately
                echo "Multi-role user found: {$user->name} (ID: {$user->id}) - Type: {$user->type}, Has landlord role\n";
            }
        }
    }
    
    public function down()
    {
        // Optional: rollback logic
    }
};
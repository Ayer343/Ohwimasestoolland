<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use Illuminate\Support\Facades\Log;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear role cache if you have caching
        \Illuminate\Support\Facades\Cache::forget('roles_list');
        
        $roles = [
            [
                'name' => 'Super Administrator',
                'slug' => 'super-admin',
                'display_name' => 'Super Administrator', // Add display_name
                'description' => 'Full system access with all permissions',
                'priority' => 1,
                'is_default' => false,
                'is_system' => true,
                'permissions' => ['*'],
                'metadata' => ['created_via' => 'seeder']
            ],
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Administrative access with user management',
                'priority' => 2,
                'is_default' => false,
                'is_system' => true,
                'permissions' => ['manage_users', 'view_reports'],
                'metadata' => ['created_via' => 'seeder']
            ],
            [
                'name' => 'Landlord',
                'slug' => 'landlord',
                'display_name' => 'Landlord',
                'description' => 'Can own and manage properties',
                'priority' => 3,
                'is_default' => false,
                'is_system' => true,
                'permissions' => ['manage_properties', 'view_tenants'],
                'metadata' => ['created_via' => 'seeder']
            ],
            [
                'name' => 'Field Agent',
                'slug' => 'field-agent',
                'display_name' => 'Field Agent',
                'description' => 'Can register properties and manage assignments',
                'priority' => 4,
                'is_default' => false,
                'is_system' => true,
                'permissions' => ['register_properties', 'view_assignments'],
                'metadata' => ['created_via' => 'seeder']
            ],
            [
                'name' => 'Security Personnel',
                'slug' => 'security-personnel',
                'display_name' => 'Security Personnel',
                'description' => 'Security staff with access control',
                'priority' => 5,
                'is_default' => false,
                'is_system' => true,
                'permissions' => ['access_control', 'view_security_logs'],
                'metadata' => ['created_via' => 'seeder']
            ],
            [
                'name' => 'Tenant',
                'slug' => 'tenant',
                'display_name' => 'Tenant',
                'description' => 'Can rent properties and make payments',
                'priority' => 6,
                'is_default' => true,
                'is_system' => true,
                'permissions' => ['view_rented_property', 'make_payments'],
                'metadata' => ['created_via' => 'seeder']
            ],
        ];

        foreach ($roles as $role) {
            try {
                // Use updateOrCreate to avoid duplicates
                $created = Role::updateOrCreate(
                    ['slug' => $role['slug']],
                    $role
                );
                
                $this->command->info("✓ Role '{$role['name']}' created/updated successfully (ID: {$created->id})");
                
            } catch (\Exception $e) {
                $this->command->error("✗ Failed to create role '{$role['name']}': " . $e->getMessage());
                Log::error("Role seeder failed", [
                    'role' => $role['slug'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Verify roles were created
        $count = Role::count();
        $this->command->info("Total roles in database: {$count}");
        
        // Show all roles
        $rolesList = Role::all(['id', 'name', 'slug', 'priority']);
        $this->command->table(
            ['ID', 'Name', 'Slug', 'Priority'],
            $rolesList->toArray()
        );
    }
}
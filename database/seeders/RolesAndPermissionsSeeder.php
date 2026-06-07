<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Orders
            'view_orders', 'create_orders', 'edit_orders', 'delete_orders',
            'approve_order', 'process_payment', 'release_order', 'prepare_order', 'deliver_order',
            // Additional order fields (new)
            'view_total', 'edit_total', 'view_contact', 'edit_contact',
            // Users
            'view_users', 'create_users', 'edit_users', 'delete_users',
            // Roles
            'view_roles', 'create_roles', 'edit_roles', 'delete_roles',
            // Permissions
            'view_permissions', 'create_permissions', 'edit_permissions', 'delete_permissions',
            'view_reports', 'generate_reports', 'view_audit_logs', 'view_settings', 'edit_settings', 'manage_system', 'view_dashboard',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $staffRole = Role::firstOrCreate(['name' => 'staff']);

        // Admin gets all permissions
        $adminRole->syncPermissions(Permission::all());

        // Manager permissions
        $managerRole->syncPermissions([
            'view_orders', 'create_orders', 'edit_orders', 'approve_order', 'release_order',
            'view_total', 'view_contact', 'view_reports', 'generate_reports', 'view_dashboard'
        ]);

        // Staff permissions
        $staffRole->syncPermissions([
            'view_orders', 'create_orders', 'process_payment', 'prepare_order', 'deliver_order',
            'view_total', 'view_contact'
        ]);
    }
}
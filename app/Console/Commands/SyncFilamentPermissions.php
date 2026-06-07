<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SyncFilamentPermissions extends Command
{
    protected $signature = 'permissions:sync';
    protected $description = 'Sync all Filament resource permissions to Spatie permission table';

    public function handle()
    {
        $resourcesPath = app_path('Filament/Resources');
        if (!File::exists($resourcesPath)) {
            $this->error('No Resources directory found!');
            return 1;
        }

        $resourceFiles = File::files($resourcesPath);
        $allPermissions = [];

        foreach ($resourceFiles as $file) {
            $className = 'App\\Filament\\Resources\\' . pathinfo($file, PATHINFO_FILENAME);
            if (!class_exists($className)) continue;

            // Try to get permission prefixes from the resource class
            $prefixes = [];
            if (method_exists($className, 'getPermissionPrefixes')) {
                $prefixes = $className::getPermissionPrefixes();
            } else {
                // Default prefixes for standard Filament resource
                $prefixes = ['view', 'view_any', 'create', 'update', 'delete', 'delete_any'];
            }

            // Get the resource name in lower case (e.g., 'order', 'user')
            $resourceName = strtolower(class_basename($className));
            $resourceName = Str::replaceLast('resource', '', $resourceName);

            foreach ($prefixes as $prefix) {
                $permissionName = $prefix . '_' . $resourceName;
                $allPermissions[] = $permissionName;
            }

            // Add custom permissions that might be used in actions (approve, release, etc.)
            // These are based on your OrderResource actions
            if ($resourceName === 'order') {
                $extra = ['approve_order', 'process_payment', 'release_order', 'prepare_order', 'deliver_order'];
                $allPermissions = array_merge($allPermissions, $extra);
            }
        }

        // Add permissions for managing users, roles, permissions if those resources exist
        $allPermissions = array_merge($allPermissions, [
            'view_users', 'create_users', 'edit_users', 'delete_users',
            'view_roles', 'create_roles', 'edit_roles', 'delete_roles',
            'view_permissions', 'create_permissions', 'edit_permissions', 'delete_permissions',
        ]);

        $allPermissions = array_unique($allPermissions);
        sort($allPermissions);

        $created = 0;
        foreach ($allPermissions as $permission) {
            if (!Permission::where('name', $permission)->exists()) {
                Permission::create(['name' => $permission, 'guard_name' => 'web']);
                $created++;
                $this->line("Created permission: <info>{$permission}</info>");
            } else {
                $this->line("Permission already exists: <comment>{$permission}</comment>");
            }
        }

        $this->info("Done! $created new permissions created.");
        return 0;
    }
}
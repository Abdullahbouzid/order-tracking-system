<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure super-admin role exists and has all permissions
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdminRole->syncPermissions(Permission::all());
// داخل دالة run()
Permission::firstOrCreate(['name' => 'set_stage_timestamp']);
// دالة run()
Permission::firstOrCreate(['name' => 'set_order_date']);   // للتحكم بزر تاريخ الطلب
Permission::firstOrCreate(['name' => 'set_stage_timestamp']); // للتحكم بأزرار التواقيت

// ثم امنحها للأدوار المطلوبة
$superAdminRole->givePermissionTo('set_order_date');
$superAdminRole->givePermissionTo('set_stage_timestamp');

// ثم امنح الصلاحية للأدوار المناسبة (مثلاً super-admin و manager)
$superAdminRole->givePermissionTo('set_stage_timestamp');
        // Create super admin user
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
            ]
        );
        $user->assignRole($superAdminRole);
    }
}
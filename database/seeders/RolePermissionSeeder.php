<?php

namespace Database\Seeders;

use App\Models\RolePermission;
use Illuminate\Database\Seeder;

/**
 * Default Teacher/Student module permissions matching their current menus (all shown).
 * Safe to rerun: existing rows (Admin's choices) are never changed; only missing modules are added.
 * php artisan db:seed --class=RolePermissionSeeder
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RolePermission::managedRoles() as $role) {
            foreach (array_keys(RolePermission::modulesFor($role)) as $module) {
                RolePermission::firstOrCreate(['role' => $role, 'module' => $module], ['allowed' => true]);
            }
        }
    }
}

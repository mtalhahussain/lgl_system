<?php

namespace Database\Seeders;

use App\Models\RolePermission;
use Illuminate\Database\Seeder;

/**
 * Go-live defaults: Teacher and Student see every module of their own portal (their default menus).
 * Staff gets nothing here; Admin grants Staff module actions manually in Users > Role permissions.
 * Safe to rerun: existing rows (Admin's choices) are never changed; only missing modules are added.
 * php artisan db:seed --class=RolePermissionSeeder
 */
class RolePermissionSeeder extends Seeder
{
    public const DEFAULT_ROLES = ['teacher', 'student'];

    public function run(): void
    {
        foreach (array_intersect(self::DEFAULT_ROLES, RolePermission::managedRoles()) as $role) {
            foreach (array_keys(RolePermission::modulesFor($role)) as $module) {
                foreach (array_keys(RolePermission::actionsFor($role, $module)) as $action) {
                    RolePermission::firstOrCreate(['role' => $role, 'module' => $module, 'action' => $action], ['allowed' => true]);
                }
            }
        }
    }
}

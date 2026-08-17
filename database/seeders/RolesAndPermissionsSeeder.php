<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app()[PermissionRegistrar::class];
        $registrar->forgetCachedPermissions();

        $catalog = config('route_permissions');
        $guard = config('auth.defaults.guard', 'web');

        $allPermissionNames = collect($catalog['role_permissions'] ?? [])
            ->flatten()
            ->merge($catalog['admin_permissions'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        foreach ($allPermissionNames as $name) {
            Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => $guard,
            ]);
        }

        $registrar->forgetCachedPermissions();

        foreach ($catalog['roles'] ?? [] as $roleName) {
            Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => $guard,
            ]);
        }

        foreach ($catalog['role_permissions'] ?? [] as $roleName => $permissions) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', $guard)
                ->firstOrFail();

            $role->syncPermissions(
                Permission::query()
                    ->where('guard_name', $guard)
                    ->whereIn('name', $permissions)
                    ->get()
            );
        }

        $admin = Role::query()
            ->where('name', 'admin')
            ->where('guard_name', $guard)
            ->firstOrFail();

        $admin->syncPermissions(
            Permission::query()
                ->where('guard_name', $guard)
                ->whereIn('name', $allPermissionNames)
                ->get()
        );

        $registrar->forgetCachedPermissions();
    }
}

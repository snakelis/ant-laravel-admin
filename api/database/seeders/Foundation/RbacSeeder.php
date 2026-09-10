<?php

namespace Database\Seeders\Foundation;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Rbac\SystemPermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (SystemPermissionCatalog::all() as $name => $metadata) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                $metadata
            );
        }

        $superAdmin = Role::query()->firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'web'],
            []
        );
        $superAdmin->givePermissionTo(Permission::query()->whereIn('name', SystemPermissionCatalog::names())->get());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

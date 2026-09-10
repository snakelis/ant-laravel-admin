<?php

namespace App\Services\Rbac;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GrantAuthorization
{
    /**
     * @param  array<int, int|string>  $permissionIds
     * @return Collection<int, Permission>
     *
     * @throws AuthorizationException
     */
    public function permissionsGrantableBy(User $actor, array $permissionIds, bool $lockForUpdate = false): Collection
    {
        $permissionIds = $this->normalizeIds($permissionIds);
        $query = Permission::query()
            ->where('guard_name', 'web')
            ->whereKey($permissionIds)
            ->orderBy('id');

        $permissions = $this->lock($query, $lockForUpdate)->get();

        if ($permissions->count() !== count($permissionIds)) {
            throw new AuthorizationException('Cannot grant unknown permissions.');
        }

        $this->assertNewPermissionsEnabled($permissions);

        if (! $this->canGrantAll($actor)) {
            $this->assertPermissionsManageable($actor, $permissions);
        }

        return $permissions;
    }

    /**
     * Validate both sides of a full permission sync while the target role is locked.
     *
     * @param  array<int, int|string>  $permissionIds
     * @return Collection<int, Permission>
     *
     * @throws AuthorizationException
     */
    public function permissionsSynchronizableBy(User $actor, Role $role, array $permissionIds): Collection
    {
        $requestedIds = $this->normalizeIds($permissionIds);
        $currentIds = $this->lockedRolePermissionIds($role);
        $allIds = $currentIds
            ->merge($requestedIds)
            ->unique()
            ->sort()
            ->values();

        $permissions = Permission::query()
            ->whereKey($allIds->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $requested = $permissions
            ->whereIn('id', $requestedIds)
            ->values();

        if ($requested->count() !== count($requestedIds)) {
            throw new AuthorizationException('Cannot grant unknown permissions.');
        }

        $this->assertNewPermissionsEnabled($requested->whereNotIn('id', $currentIds->all()));

        $this->assertRolePermissionsManageable($actor, $role, $permissions);

        return $requested;
    }

    /**
     * Assert that a locked target role and its current permission set are
     * within the actor's manageable boundary.
     *
     * @throws AuthorizationException
     */
    public function assertRoleManageableBy(User $actor, Role $role): void
    {
        $permissionIds = $this->lockedRolePermissionIds($role);
        $permissions = Permission::query()
            ->whereKey($permissionIds->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $this->assertRolePermissionsManageable($actor, $role, $permissions);
    }

    /**
     * Lock every user-role assignment that would be removed by deleting the
     * already locked target role.
     */
    public function lockRoleAssignments(Role $role): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $rolePivotKey = $columnNames['role_pivot_key'] ?? 'role_id';
        $modelKey = $columnNames['model_morph_key'] ?? 'model_id';

        DB::table($tableNames['model_has_roles'])
            ->where($rolePivotKey, $role->getKey())
            ->orderBy('model_type')
            ->orderBy($modelKey)
            ->lockForUpdate()
            ->get([$rolePivotKey, 'model_type', $modelKey]);
    }

    /**
     * @param  array<int, int|string>  $roleIds
     * @return Collection<int, Role>
     *
     * @throws AuthorizationException
     */
    public function rolesAssignableBy(User $actor, array $roleIds, bool $lockForUpdate = false): Collection
    {
        $roleIds = $this->normalizeIds($roleIds);
        $query = Role::query()
            ->with('permissions')
            ->where('guard_name', 'web')
            ->whereKey($roleIds)
            ->orderBy('id');

        $roles = $this->lock($query, $lockForUpdate)->get();

        if ($roles->count() !== count($roleIds)) {
            throw new AuthorizationException('Cannot assign unknown roles.');
        }

        if (! $this->canGrantAll($actor)) {
            $this->assertRolesManageable($actor, $roles);
        }

        return $roles;
    }

    /**
     * Validate both sides of a full role sync while the target user is locked.
     *
     * @param  array<int, int|string>  $roleIds
     * @return Collection<int, Role>
     *
     * @throws AuthorizationException
     */
    public function rolesSynchronizableBy(User $actor, User $target, array $roleIds): Collection
    {
        $requestedIds = $this->normalizeIds($roleIds);
        $currentIds = $target->roles()
            ->where('roles.guard_name', 'web')
            ->pluck('roles.id')
            ->map(fn (mixed $id): int => (int) $id);
        $allIds = $currentIds
            ->merge($requestedIds)
            ->unique()
            ->sort()
            ->values();

        $roles = Role::query()
            ->with('permissions')
            ->where('guard_name', 'web')
            ->whereKey($allIds->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $requested = $roles
            ->whereIn('id', $requestedIds)
            ->values();

        if ($requested->count() !== count($requestedIds)) {
            throw new AuthorizationException('Cannot assign unknown roles.');
        }

        if (! $this->canGrantAll($actor)) {
            $this->assertRolesManageable($actor, $roles);
        }

        return $requested;
    }

    /**
     * Assert that the target account's protected role and effective permissions
     * are within the actor's manageable set. The target user row must already
     * be locked by the caller.
     *
     * @throws AuthorizationException
     */
    public function assertUserManageableBy(User $actor, User $target): void
    {
        if ($this->canGrantAll($actor)) {
            return;
        }

        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $rolePivotKey = $columnNames['role_pivot_key'] ?? 'role_id';
        $permissionPivotKey = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $modelKey = $columnNames['model_morph_key'] ?? 'model_id';

        $roleIds = DB::table($tableNames['model_has_roles'])
            ->where('model_type', $target->getMorphClass())
            ->where($modelKey, $target->getKey())
            ->orderBy($rolePivotKey)
            ->lockForUpdate()
            ->pluck($rolePivotKey)
            ->map(fn (mixed $id): int => (int) $id);
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereKey($roleIds->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($roles->contains(fn (Role $role): bool => $role->name === 'super-admin')) {
            throw new AuthorizationException('Cannot manage protected user accounts.');
        }

        $rolePermissionIds = DB::table($tableNames['role_has_permissions'])
            ->whereIn($rolePivotKey, $roleIds->all())
            ->orderBy($permissionPivotKey)
            ->lockForUpdate()
            ->pluck($permissionPivotKey);
        $directPermissionIds = DB::table($tableNames['model_has_permissions'])
            ->where('model_type', $target->getMorphClass())
            ->where($modelKey, $target->getKey())
            ->orderBy($permissionPivotKey)
            ->lockForUpdate()
            ->pluck($permissionPivotKey);
        $effectivePermissionIds = $rolePermissionIds
            ->merge($directPermissionIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();
        $effectivePermissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereKey($effectivePermissionIds->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $this->assertPermissionsManageable($actor, $effectivePermissions);
    }

    public function canGrantAll(User $actor): bool
    {
        return $actor->can('system.permission.grant-all') || $actor->hasRole('super-admin');
    }

    /** @param Collection<int, Permission> $permissions */
    private function assertNewPermissionsEnabled(Collection $permissions): void
    {
        if ($permissions->contains(fn (Permission $permission): bool => ! $permission->is_enabled)) {
            throw new AuthorizationException('Cannot grant disabled permissions.');
        }
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     *
     * @throws AuthorizationException
     */
    private function assertPermissionsManageable(User $actor, Collection $permissions): void
    {
        $actorPermissionNames = $actor->getAllPermissions()->pluck('name');
        $unmanageable = $permissions
            ->reject(fn (Permission $permission): bool => $actorPermissionNames->contains($permission->name))
            ->pluck('name')
            ->values();

        if ($unmanageable->isNotEmpty()) {
            throw new AuthorizationException('Cannot grant permissions that the current user does not have or revoke permissions outside the manageable set.');
        }
    }

    /**
     * @param  Collection<int, Role>  $roles
     *
     * @throws AuthorizationException
     */
    private function assertRolesManageable(User $actor, Collection $roles): void
    {
        if ($roles->contains(fn (Role $role): bool => $role->name === 'super-admin')) {
            throw new AuthorizationException('Cannot manage protected roles.');
        }

        $actorPermissionNames = $actor->getAllPermissions()->pluck('name');
        $unmanageable = $roles
            ->flatMap(fn (Role $role): Collection => $role->permissions)
            ->unique('name')
            ->reject(fn (Permission $permission): bool => $actorPermissionNames->contains($permission->name))
            ->pluck('name')
            ->values();

        if ($unmanageable->isNotEmpty()) {
            throw new AuthorizationException('Cannot assign roles containing permissions that the current user does not have or revoke roles outside the manageable set.');
        }
    }

    /**
     * @return Collection<int, int>
     */
    private function lockedRolePermissionIds(Role $role): Collection
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $rolePivotKey = $columnNames['role_pivot_key'] ?? 'role_id';
        $permissionPivotKey = $columnNames['permission_pivot_key'] ?? 'permission_id';

        return DB::table($tableNames['role_has_permissions'])
            ->where($rolePivotKey, $role->getKey())
            ->orderBy($permissionPivotKey)
            ->lockForUpdate()
            ->pluck($permissionPivotKey)
            ->map(fn (mixed $id): int => (int) $id);
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     *
     * @throws AuthorizationException
     */
    private function assertRolePermissionsManageable(User $actor, Role $role, Collection $permissions): void
    {
        if ($role->name === 'super-admin') {
            throw new AuthorizationException('Cannot manage protected roles.');
        }

        if (! $this->canGrantAll($actor)) {
            $this->assertPermissionsManageable($actor, $permissions);
        }
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function lock(Builder $query, bool $lockForUpdate): Builder
    {
        return $lockForUpdate ? $query->lockForUpdate() : $query;
    }
}

<?php

namespace App\Services\Rbac;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/** 页面操作提示的只读快照；写操作仍由 can、Policy 和事务内授权校验。 */
class RecordCapabilities
{
    public function user(User $actor, User $target): array
    {
        $reason = null;
        if (! $this->all($actor)) {
            if ($target->hasRole('super-admin')) {
                $reason = '系统管理员账号受保护';
            } elseif ($target->getAllPermissions()->pluck('name')->diff($actor->getAllPermissions()->pluck('name'))->isNotEmpty()) {
                $reason = '该账号包含超出你管理范围的权限';
            }
        }

        return $this->actions($actor, 'user', $reason, $actor->is($target) ? '不能删除当前登录账号' : $reason);
    }

    public function role(User $actor, Role $role): array
    {
        $reason = $role->name === 'super-admin' ? '系统内置角色受保护' : null;
        if (! $reason && ! $this->all($actor) && $role->permissions->pluck('name')->diff($actor->getAllPermissions()->pluck('name'))->isNotEmpty()) {
            $reason = '该角色包含超出你管理范围的权限';
        }

        return $this->actions($actor, 'role', $reason, $reason);
    }

    public function permission(User $actor, Permission $permission): array
    {
        $protected = $permission->isProtectedSystemPermission();
        $attributes = $permission->getAttributes();
        $referenced = array_key_exists('menu_reference', $attributes)
            ? (bool) ($permission->menu_reference || $permission->role_reference || $permission->direct_reference)
            : ($permission->isReferencedByMenu() || $permission->isAssignedToRole() || $permission->isAssignedDirectlyToModel());
        $updateReason = $protected && ! $this->all($actor) ? '仅完整授权管理员可修改系统权限的展示信息' : null;
        $deleteReason = $protected ? '系统内置权限不能删除' : ($referenced ? '该权限已被分配或被菜单引用' : null);

        return $this->actions($actor, 'permission', $updateReason, $deleteReason) + [
            'rename' => ! $protected && ! $referenced && $this->can($actor, 'system.permission.update'),
            'change_status' => ! $protected && $this->can($actor, 'system.permission.update'),
            'protected' => $protected,
        ];
    }

    private function actions(User $actor, string $resource, ?string $updateReason, ?string $deleteReason): array
    {
        $updateReason = $this->can($actor, "system.{$resource}.update") ? $updateReason : '没有编辑权限';
        $deleteReason = $this->can($actor, "system.{$resource}.delete") ? $deleteReason : '没有删除权限';

        return ['update' => $updateReason === null, 'delete' => $deleteReason === null,
            'update_reason' => $updateReason, 'delete_reason' => $deleteReason];
    }

    private function can(User $actor, string $permission): bool
    {
        $key = 'record-capability:'.$actor->id.':'.$permission;
        $attributes = request()->attributes;
        if (! $attributes->has($key)) {
            $attributes->set($key, $actor->can($permission));
        }

        return $attributes->get($key);
    }

    private function all(User $actor): bool
    {
        return $this->can($actor, 'system.permission.grant-all') || $actor->hasRole('super-admin');
    }
}

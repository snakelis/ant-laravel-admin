<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use App\Services\Rbac\GrantAuthorization;
use Illuminate\Auth\Access\Response;

class PermissionPolicy
{
    public function __construct(private readonly GrantAuthorization $grantAuthorization) {}

    public function view(User $user, Permission $permission): bool
    {
        return $user->can('system.permission.view');
    }

    public function update(User $user, Permission $permission): Response
    {
        if (! $user->can('system.permission.update')) {
            return Response::deny();
        }

        if ($permission->isProtectedSystemPermission() && ! $this->grantAuthorization->canGrantAll($user)) {
            return Response::deny('Only grant-all operators can update system permission display metadata.');
        }

        return Response::allow();
    }

    public function delete(User $user, Permission $permission): Response
    {
        if (! $user->can('system.permission.delete')) {
            return Response::deny();
        }

        if ($permission->isProtectedSystemPermission()) {
            return Response::deny('System or route-bound permissions cannot be deleted.');
        }

        if ($permission->isReferencedByMenu()) {
            return Response::deny('Permissions referenced by menus cannot be deleted.');
        }

        if ($permission->isAssignedToRole() || $permission->isAssignedDirectlyToModel()) {
            return Response::deny('Assigned permissions cannot be deleted.');
        }

        return Response::allow();
    }
}

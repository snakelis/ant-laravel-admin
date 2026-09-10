<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RolePolicy
{
    public function view(User $user, Role $role): bool
    {
        return $user->can('system.role.view');
    }

    public function update(User $user, Role $role): Response
    {
        if (! $user->can('system.role.update')) {
            return Response::deny();
        }

        if ($role->name === 'super-admin') {
            return Response::deny('The protected system role cannot be updated.');
        }

        return Response::allow();
    }

    public function delete(User $user, Role $role): Response
    {
        if (! $user->can('system.role.delete')) {
            return Response::deny();
        }

        if ($role->name === 'super-admin') {
            return Response::deny('The protected system role cannot be deleted.');
        }

        return Response::allow();
    }
}

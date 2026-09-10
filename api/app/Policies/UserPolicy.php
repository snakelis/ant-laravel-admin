<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function view(User $actor, User $user): bool
    {
        return $actor->can('system.user.view');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->can('system.user.update');
    }

    public function delete(User $actor, User $user): Response
    {
        if (! $actor->can('system.user.delete')) {
            return Response::deny();
        }

        if ($actor->is($user)) {
            return Response::deny('Users cannot delete their own account.');
        }

        return Response::allow();
    }
}

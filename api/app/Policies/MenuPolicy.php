<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

class MenuPolicy
{
    public function view(User $user, Menu $menu): bool
    {
        return $user->can('system.menu.view');
    }

    public function update(User $user, Menu $menu): bool
    {
        return $user->can('system.menu.update');
    }

    public function delete(User $user, Menu $menu): bool
    {
        return $user->can('system.menu.delete');
    }
}

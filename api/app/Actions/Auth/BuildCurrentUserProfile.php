<?php

namespace App\Actions\Auth;

use App\Http\Resources\Api\V1\System\MenuResource;
use App\Models\Menu;
use App\Models\User;
use App\Services\MenuTreeBuilder;

class BuildCurrentUserProfile
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        $permissions = $user->getEnabledPermissions()
            ->pluck('name')
            ->sort()
            ->values();

        $roles = $user->roles()
            ->orderBy('name')
            ->pluck('name')
            ->values();

        $menus = Menu::query()
            ->where('is_enabled', true)
            ->where('is_visible', true)
            ->where(function ($query) use ($permissions): void {
                $query->whereNull('permission_name')
                    ->orWhereIn('permission_name', $permissions->all());
            })
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toJSON(),
                'created_at' => $user->created_at?->toJSON(),
                'updated_at' => $user->updated_at?->toJSON(),
            ],
            'roles' => $roles,
            'permissions' => $permissions,
            'menus' => MenuResource::collection(MenuTreeBuilder::build($menus))->resolve(),
        ];
    }
}

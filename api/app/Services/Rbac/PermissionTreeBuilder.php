<?php

namespace App\Services\Rbac;

use App\Models\Permission;
use Illuminate\Support\Collection;

class PermissionTreeBuilder
{
    /**
     * @param  Collection<int, Permission>  $permissions
     * @return array<int, array<string, mixed>>
     */
    public function build(Collection $permissions): array
    {
        return $permissions
            ->groupBy(fn (Permission $permission) => $permission->module_code ?: $this->segment($permission->name, 0))
            ->map(function (Collection $modulePermissions, string $moduleCode): array {
                $first = $modulePermissions->sortBy([['sort', 'asc'], ['name', 'asc']])->first();

                return [
                    'key' => $moduleCode,
                    'type' => 'module',
                    'title' => $first?->module_title ?: $moduleCode,
                    'sort' => (int) ($first?->sort ?? 0),
                    'children' => $modulePermissions
                        ->groupBy(fn (Permission $permission) => $permission->resource_code ?: $this->segment($permission->name, 1))
                        ->map(function (Collection $resourcePermissions, string $resourceCode) use ($moduleCode): array {
                            $first = $resourcePermissions->sortBy([['sort', 'asc'], ['name', 'asc']])->first();

                            return [
                                'key' => $moduleCode.'.'.$resourceCode,
                                'type' => 'resource',
                                'title' => $first?->resource_title ?: $resourceCode,
                                'sort' => (int) ($first?->sort ?? 0),
                                'children' => $resourcePermissions
                                    ->sortBy([['sort', 'asc'], ['name', 'asc']])
                                    ->values()
                                    ->map(fn (Permission $permission): array => [
                                        'key' => $permission->name,
                                        'type' => 'permission',
                                        'id' => $permission->id,
                                        'name' => $permission->name,
                                        'title' => $permission->title ?: $permission->name,
                                        'action_code' => $permission->action_code ?: $this->segment($permission->name, 2),
                                        'action_title' => $permission->action_title ?: ($permission->title ?: $permission->name),
                                        'is_system' => $permission->is_system,
                                        'is_enabled' => $permission->is_enabled,
                                    ])
                                    ->all(),
                            ];
                        })
                        ->sortBy([['sort', 'asc'], ['key', 'asc']])
                        ->values()
                        ->map(fn (array $resource): array => $this->withoutSort($resource))
                        ->all(),
                ];
            })
            ->sortBy([['sort', 'asc'], ['key', 'asc']])
            ->values()
            ->map(fn (array $module): array => $this->withoutSort($module))
            ->all();
    }

    private function segment(string $permissionName, int $index): string
    {
        return explode('.', $permissionName)[$index] ?? $permissionName;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function withoutSort(array $node): array
    {
        unset($node['sort']);

        return $node;
    }
}

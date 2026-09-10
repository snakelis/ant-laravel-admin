<?php

namespace App\Models;

use App\Services\Rbac\SystemPermissionCatalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as SpatiePermission;

#[Fillable([
    'name',
    'guard_name',
    'title',
    'module_code',
    'module_title',
    'resource_code',
    'resource_title',
    'action_code',
    'action_title',
    'description',
    'sort',
    'is_system',
    'is_enabled',
])]
class Permission extends SpatiePermission
{
    public function scopeWithUsage(Builder $query): void
    {
        $tables = config('permission.table_names');
        $key = config('permission.column_names.permission_pivot_key') ?? 'permission_id';
        $query->addSelect([
            'menu_reference' => Menu::query()->select('id')->whereColumn('permission_name', 'permissions.name')->limit(1),
            'role_reference' => DB::table($tables['role_has_permissions'])->select($key)->whereColumn($key, 'permissions.id')->limit(1),
            'direct_reference' => DB::table($tables['model_has_permissions'])->select($key)->whereColumn($key, 'permissions.id')->limit(1),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'is_system' => 'boolean',
            'is_enabled' => 'boolean',
        ];
    }

    public function isProtectedSystemPermission(): bool
    {
        return $this->is_system || str_starts_with($this->name, 'system.') || SystemPermissionCatalog::get($this->name) !== null;
    }

    public function isReferencedByMenu(): bool
    {
        return Menu::query()
            ->where('permission_name', $this->name)
            ->exists();
    }

    public function isAssignedToRole(): bool
    {
        return DB::table(config('permission.table_names.role_has_permissions'))
            ->where(config('permission.column_names.permission_pivot_key') ?? 'permission_id', $this->id)
            ->exists();
    }

    public function isAssignedDirectlyToModel(): bool
    {
        return DB::table(config('permission.table_names.model_has_permissions'))
            ->where(config('permission.column_names.permission_pivot_key') ?? 'permission_id', $this->id)
            ->exists();
    }

    public function hasRenameSensitiveReferences(): bool
    {
        return $this->isProtectedSystemPermission() || $this->isReferencedByMenu() || $this->isAssignedToRole() || $this->isAssignedDirectlyToModel();
    }
}

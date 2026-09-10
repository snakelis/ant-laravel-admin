<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Auth\PasswordPolicy;
use App\Services\Rbac\GrantAuthorization;
use App\Services\Rbac\PermissionTreeBuilder;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ManagementOptionsController
{
    #[OA\Get(path: '/management-options/{kind}', operationId: 'managementOptions', security: [['sanctumSession' => []]], tags: ['Permissions'], parameters: [new OA\Parameter(name: 'kind', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['user-roles', 'role-permissions', 'menu-permissions', 'user-password-policy']))], responses: [new OA\Response(response: 200, description: '完整表单选项。用户角色仅返回可分配的 id/name；角色权限返回权限树；菜单权限返回启用的查看权限；user-password-policy 返回 min_length、max_length、mixed_case、numbers、symbols 和 description，不包含凭据。需要对应模块的新建或编辑权限。'), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden')])]
    public function __invoke(Request $request, string $kind, GrantAuthorization $grants, PermissionTreeBuilder $tree): JsonResponse
    {
        $resource = match ($kind) {
            'user-roles', 'user-password-policy' => 'user',
            'role-permissions' => 'role',
            'menu-permissions' => 'menu',
        };
        $actor = $request->user();
        abort_unless($actor->can("system.{$resource}.create") || $actor->can("system.{$resource}.update"), 403);

        if ($kind === 'user-password-policy') {
            return ApiResponse::success(app(PasswordPolicy::class)->settings());
        }

        if ($kind === 'user-roles') {
            $query = Role::query()->where('guard_name', 'web');
            if (! $grants->canGrantAll($actor)) {
                $names = $actor->getAllPermissions()->pluck('name');
                $query->where('name', '!=', 'super-admin')->whereDoesntHave('permissions', fn ($query) => $query->whereNotIn('name', $names));
            }

            return ApiResponse::success($query->orderBy('name')->get(['id', 'name']));
        }

        $query = Permission::query()->where('guard_name', 'web')->orderBy('sort')->orderBy('name');
        if ($kind === 'role-permissions') {
            return ApiResponse::success($tree->build($query->get()));
        }

        return ApiResponse::success($query->where('is_enabled', true)->get([
            'id', 'name', 'title', 'resource_title', 'action_title', 'action_code',
        ])->filter(fn (Permission $permission) => in_array($permission->action_code ?: collect(explode('.', $permission->name))->last(), ['view', 'list', 'read'], true))->values());
    }
}

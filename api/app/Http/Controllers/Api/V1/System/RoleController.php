<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Requests\Api\V1\System\RoleStoreRequest;
use App\Http\Requests\Api\V1\System\RoleUpdateRequest;
use App\Http\Resources\Api\V1\System\RoleResource;
use App\Models\Role;
use App\Services\Rbac\GrantAuthorization;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class RoleController
{
    #[OA\Get(path: '/roles', operationId: 'listRoles', security: [['sanctumSession' => []]], tags: ['Roles'], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope'))])]
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $search = trim((string) $request->query('search', ''));

        $paginator = Role::query()
            ->with('permissions')
            ->where('guard_name', 'web')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage);

        return ApiResponse::paginated(
            paginator: $paginator,
            data: RoleResource::collection($paginator->getCollection())->resolve(),
        );
    }

    #[OA\Post(path: '/roles', operationId: 'createRole', security: [['sanctumSession' => []]], tags: ['Roles'], responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')), new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope'))])]
    public function store(RoleStoreRequest $request, GrantAuthorization $grantAuthorization): JsonResponse
    {
        $role = DB::transaction(function () use ($request, $grantAuthorization): Role {
            $permissions = $request->has('permission_ids')
                ? $grantAuthorization->permissionsGrantableBy($request->user(), $request->input('permission_ids', []), lockForUpdate: true)
                : collect();
            $role = Role::query()->create([
                'name' => $request->string('name')->toString(),
                'guard_name' => 'web',
            ]);

            if ($request->has('permission_ids')) {
                $role->syncPermissions($permissions);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });

        return ApiResponse::success(
            data: RoleResource::make($role->load('permissions')),
            message: 'Created.',
            status: Response::HTTP_CREATED,
        );
    }

    #[OA\Get(path: '/roles/{role}', operationId: 'getRole', security: [['sanctumSession' => []]], tags: ['Roles'], parameters: [new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope'))])]
    public function show(Role $role): JsonResponse
    {
        return ApiResponse::success(RoleResource::make($role->load('permissions')));
    }

    #[OA\Patch(path: '/roles/{role}', operationId: 'updateRole', security: [['sanctumSession' => []]], tags: ['Roles'], parameters: [new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')), new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope'))])]
    public function update(RoleUpdateRequest $request, Role $role, GrantAuthorization $grantAuthorization): JsonResponse
    {
        DB::transaction(function () use ($request, $role, $grantAuthorization): void {
            $lockedRole = Role::query()
                ->whereKey($role->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $permissions = $request->has('permission_ids')
                ? $grantAuthorization->permissionsSynchronizableBy($request->user(), $lockedRole, $request->input('permission_ids', []))
                : collect();

            if (! $request->has('permission_ids')) {
                $grantAuthorization->assertRoleManageableBy($request->user(), $lockedRole);
            }

            if ($request->has('name')) {
                $lockedRole->forceFill(['name' => $request->string('name')->toString()])->save();
            }

            if ($request->has('permission_ids')) {
                $lockedRole->syncPermissions($permissions);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        return ApiResponse::success(RoleResource::make($role->refresh()->load('permissions')));
    }

    #[OA\Delete(path: '/roles/{role}', operationId: 'deleteRole', security: [['sanctumSession' => []]], tags: ['Roles'], parameters: [new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')), new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope'))])]
    public function destroy(Request $request, Role $role, GrantAuthorization $grantAuthorization): JsonResponse
    {
        DB::transaction(function () use ($request, $role, $grantAuthorization): void {
            $lockedRole = Role::query()
                ->whereKey($role->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $grantAuthorization->assertRoleManageableBy($request->user(), $lockedRole);
            $grantAuthorization->lockRoleAssignments($lockedRole);
            $lockedRole->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        return ApiResponse::success(message: 'Deleted.');
    }
}

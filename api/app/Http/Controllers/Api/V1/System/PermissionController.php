<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Requests\Api\V1\System\PermissionIndexRequest;
use App\Http\Requests\Api\V1\System\PermissionStoreRequest;
use App\Http\Requests\Api\V1\System\PermissionUpdateRequest;
use App\Http\Resources\Api\V1\System\PermissionResource;
use App\Models\Permission;
use App\Services\Rbac\PermissionTreeBuilder;
use App\Support\Api\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class PermissionController
{
    #[OA\Get(
        path: '/permissions',
        operationId: 'listPermissions',
        security: [['sanctumSession' => []]],
        tags: ['Permissions'],
        parameters: [
            new OA\Parameter(name: 'is_system', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'is_enabled', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'resource_code', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'module_code', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tree', description: '为 true 时返回权限树；此模式不分页。', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'enabled_only', description: '仅返回启用权限。', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'search', description: '按权限码、标题、模块、资源或操作标题模糊搜索。', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', description: '分页列表页码；tree=true 时忽略。', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
            new OA\Parameter(name: 'per_page', description: '分页列表每页数量，范围 1-200；tree=true 时忽略。', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 200, default: 100)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: '分页权限列表或权限树。',
                content: new OA\JsonContent(oneOf: [
                    new OA\Schema(ref: '#/components/schemas/PermissionListResponse'),
                    new OA\Schema(ref: '#/components/schemas/PermissionTreeResponse'),
                ])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope')),
        ]
    )]
    public function index(PermissionIndexRequest $request, PermissionTreeBuilder $treeBuilder): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = Permission::query()
            ->withUsage()
            ->where('guard_name', 'web')
            ->when($request->filled('module_code'), fn ($query) => $query->where('module_code', $request->string('module_code')->toString()))
            ->when($request->filled('resource_code'), fn ($query) => $query->where('resource_code', $request->string('resource_code')->toString()))
            ->when($request->has('is_enabled'), fn ($query) => $query->where('is_enabled', $request->boolean('is_enabled')))
            ->when($request->has('is_system'), fn ($query) => $query->where('is_system', $request->boolean('is_system')))
            ->when($request->boolean('enabled_only'), fn ($query) => $query->where('is_enabled', true))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('module_title', 'like', "%{$search}%")
                        ->orWhere('resource_title', 'like', "%{$search}%")
                        ->orWhere('action_title', 'like', "%{$search}%");
                });
            })
            ->orderBy('module_code')
            ->orderBy('resource_code')
            ->orderBy('sort')
            ->orderBy('name');

        if ($request->boolean('tree')) {
            return ApiResponse::success($treeBuilder->build($query->get()));
        }

        $perPage = min(max($request->integer('per_page', 100), 1), 200);
        $paginator = $query->paginate($perPage);

        return ApiResponse::paginated(
            paginator: $paginator,
            data: PermissionResource::collection($paginator->getCollection())->resolve(),
        );
    }

    #[OA\Post(path: '/permissions', operationId: 'createPermission', security: [['sanctumSession' => []]], tags: ['Permissions'], responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')), new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope'))])]
    public function store(PermissionStoreRequest $request): JsonResponse
    {
        $permission = DB::transaction(function () use ($request): Permission {
            $permission = Permission::query()->create($this->payload($request->validated()) + [
                'guard_name' => 'web',
                'is_system' => false,
            ]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $permission;
        });

        return ApiResponse::success(
            data: PermissionResource::make($permission),
            message: 'Created.',
            status: Response::HTTP_CREATED,
        );
    }

    #[OA\Get(path: '/permissions/{permission}', operationId: 'getPermission', security: [['sanctumSession' => []]], tags: ['Permissions'], parameters: [new OA\Parameter(name: 'permission', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope'))])]
    public function show(Permission $permission): JsonResponse
    {
        return ApiResponse::success(PermissionResource::make($permission));
    }

    #[OA\Patch(path: '/permissions/{permission}', operationId: 'updatePermission', security: [['sanctumSession' => []]], tags: ['Permissions'], parameters: [new OA\Parameter(name: 'permission', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')), new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope'))])]
    public function update(PermissionUpdateRequest $request, Permission $permission): JsonResponse
    {
        DB::transaction(function () use ($request, $permission): void {
            $permission = Permission::query()
                ->whereKey($permission->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $validated = $request->validated();

            if ($permission->isProtectedSystemPermission()) {
                if (array_key_exists('name', $validated) && $validated['name'] !== $permission->name) {
                    throw new AuthorizationException('System permissions cannot be renamed.');
                }

                if (array_key_exists('is_enabled', $validated) && ! $request->boolean('is_enabled')) {
                    throw new AuthorizationException('System permissions cannot be disabled.');
                }

                $validated = array_intersect_key($validated, array_flip([
                    'title',
                    'module_title',
                    'resource_title',
                    'action_title',
                    'description',
                    'sort',
                    'is_enabled',
                ]));
                $permission->fill($validated)->save();
            } else {
                if (array_key_exists('name', $validated) && $validated['name'] !== $permission->name && $permission->hasRenameSensitiveReferences()) {
                    throw new AuthorizationException('Protected or referenced permissions cannot be renamed.');
                }

                $permission->fill($this->payload($validated, $permission->name))->save();
            }

            if (array_key_exists('name', $validated) || array_key_exists('is_enabled', $validated)) {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        });

        return ApiResponse::success(PermissionResource::make($permission->refresh()));
    }

    #[OA\Delete(path: '/permissions/{permission}', operationId: 'deletePermission', security: [['sanctumSession' => []]], tags: ['Permissions'], parameters: [new OA\Parameter(name: 'permission', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')), new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope'))])]
    public function destroy(Permission $permission): JsonResponse
    {
        DB::transaction(function () use ($permission): void {
            $permission->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        return ApiResponse::success(message: 'Deleted.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated, ?string $currentName = null): array
    {
        $name = (string) ($validated['name'] ?? $currentName);
        $segments = explode('.', $name);
        $defaults = [
            'module_code' => $segments[0] ?? null,
            'resource_code' => $segments[1] ?? null,
            'action_code' => $segments[2] ?? null,
        ];

        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $validated) && $value !== null) {
                $validated[$key] = $value;
            }
        }

        return $validated;
    }
}

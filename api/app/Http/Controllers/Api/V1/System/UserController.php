<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Requests\Api\V1\System\UserStoreRequest;
use App\Http\Requests\Api\V1\System\UserUpdateRequest;
use App\Http\Resources\Api\V1\System\UserResource;
use App\Models\User;
use App\Services\Rbac\GrantAuthorization;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class UserController
{
    #[OA\Get(path: '/users', operationId: 'listUsers', security: [['sanctumSession' => []]], tags: ['Users'], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')), new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope')), new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope'))])]
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->perPage($request);
        $search = trim((string) $request->query('search', ''));

        $paginator = User::query()
            ->with(['roles.permissions', 'permissions'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::paginated(
            paginator: $paginator,
            data: UserResource::collection($paginator->getCollection())->resolve(),
        );
    }

    #[OA\Post(path: '/users', operationId: 'createUser', security: [['sanctumSession' => []]], tags: ['Users'], responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')), new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope'))])]
    public function store(UserStoreRequest $request, GrantAuthorization $grantAuthorization): JsonResponse
    {
        $user = DB::transaction(function () use ($request, $grantAuthorization): User {
            $roles = $request->has('role_ids')
                ? $grantAuthorization->rolesAssignableBy($request->user(), $request->input('role_ids', []), lockForUpdate: true)
                : collect();
            $data = $request->safe()->except('role_ids');
            $user = User::query()->create($data);

            if ($request->has('role_ids')) {
                $user->syncRoles($roles);
            }

            return $user;
        });

        return ApiResponse::success(
            data: UserResource::make($user->load('roles.permissions')),
            message: 'Created.',
            status: Response::HTTP_CREATED,
        );
    }

    #[OA\Get(path: '/users/{user}', operationId: 'getUser', security: [['sanctumSession' => []]], tags: ['Users'], parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')), new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope'))])]
    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(UserResource::make($user->load('roles.permissions')));
    }

    #[OA\Patch(path: '/users/{user}', operationId: 'updateUser', security: [['sanctumSession' => []]], tags: ['Users'], parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')), new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope'))])]
    public function update(UserUpdateRequest $request, User $user, GrantAuthorization $grantAuthorization): JsonResponse
    {
        DB::transaction(function () use ($request, $user, $grantAuthorization): void {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $grantAuthorization->assertUserManageableBy($request->user(), $lockedUser);
            $roles = $request->has('role_ids')
                ? $grantAuthorization->rolesSynchronizableBy($request->user(), $lockedUser, $request->input('role_ids', []))
                : collect();
            $data = $request->safe()->except('role_ids');

            if (array_key_exists('password', $data) && $data['password'] === null) {
                unset($data['password']);
            }

            $lockedUser->fill($data)->save();

            if ($request->has('role_ids')) {
                $lockedUser->syncRoles($roles);
            }
        });

        return ApiResponse::success(UserResource::make($user->refresh()->load('roles.permissions')));
    }

    #[OA\Delete(path: '/users/{user}', operationId: 'deleteUser', security: [['sanctumSession' => []]], tags: ['Users'], parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')), new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope'))])]
    public function destroy(Request $request, User $user, GrantAuthorization $grantAuthorization): JsonResponse
    {
        DB::transaction(function () use ($request, $user, $grantAuthorization): void {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $grantAuthorization->assertUserManageableBy($request->user(), $lockedUser);
            $lockedUser->delete();
        });

        return ApiResponse::success(message: 'Deleted.');
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}

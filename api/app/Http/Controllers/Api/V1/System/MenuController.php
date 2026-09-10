<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Requests\Api\V1\System\MenuStoreRequest;
use App\Http\Requests\Api\V1\System\MenuUpdateRequest;
use App\Http\Resources\Api\V1\System\MenuResource;
use App\Models\Menu;
use App\Services\MenuTreeBuilder;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class MenuController
{
    #[OA\Get(path: '/menus', operationId: 'listMenus', security: [['sanctumSession' => []]], tags: ['Menus'], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope'))])]
    public function index(Request $request): JsonResponse
    {
        $query = Menu::query()
            ->when($request->boolean('enabled_only'), fn ($query) => $query->where('is_enabled', true))
            ->when(trim((string) $request->query('search', '')) !== '', function ($query) use ($request): void {
                $search = trim((string) $request->query('search', ''));

                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('path', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort')
            ->orderBy('id');

        if ($request->boolean('tree')) {
            return ApiResponse::success(MenuResource::collection(MenuTreeBuilder::build($query->get()))->resolve());
        }

        $paginator = $query->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return ApiResponse::paginated(
            paginator: $paginator,
            data: MenuResource::collection($paginator->getCollection())->resolve(),
        );
    }

    #[OA\Post(path: '/menus', operationId: 'createMenu', security: [['sanctumSession' => []]], tags: ['Menus'], responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')), new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope'))])]
    public function store(MenuStoreRequest $request): JsonResponse
    {
        $menu = Menu::query()->create($request->validated());

        return ApiResponse::success(
            data: MenuResource::make($menu),
            message: 'Created.',
            status: Response::HTTP_CREATED,
        );
    }

    #[OA\Get(path: '/menus/{menu}', operationId: 'getMenu', security: [['sanctumSession' => []]], tags: ['Menus'], parameters: [new OA\Parameter(name: 'menu', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope'))])]
    public function show(Menu $menu): JsonResponse
    {
        return ApiResponse::success(MenuResource::make($menu->load('children')));
    }

    #[OA\Patch(path: '/menus/{menu}', operationId: 'updateMenu', security: [['sanctumSession' => []]], tags: ['Menus'], parameters: [new OA\Parameter(name: 'menu', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')), new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope'))])]
    public function update(MenuUpdateRequest $request, Menu $menu): JsonResponse
    {
        $menu->fill($request->validated())->save();

        return ApiResponse::success(MenuResource::make($menu->refresh()->load('children')));
    }

    #[OA\Delete(path: '/menus/{menu}', operationId: 'deleteMenu', security: [['sanctumSession' => []]], tags: ['Menus'], parameters: [new OA\Parameter(name: 'menu', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')), new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope'))])]
    public function destroy(Menu $menu): JsonResponse
    {
        $menu->delete();

        return ApiResponse::success(message: 'Deleted.');
    }
}

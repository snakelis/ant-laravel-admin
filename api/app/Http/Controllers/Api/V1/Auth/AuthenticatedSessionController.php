<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\BuildCurrentUserProfile;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

class AuthenticatedSessionController
{
    #[OA\Post(
        path: '/auth/login',
        operationId: 'login',
        description: '登录前应先访问 /sanctum/csrf-cookie 获取 CSRF Cookie。',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                    new OA\Property(property: 'remember', type: 'boolean', example: false),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope')),
            new OA\Response(response: 429, description: '登录限流，Retry-After 响应头表示等待秒数。', headers: [new OA\Header(header: 'Retry-After', schema: new OA\Schema(type: 'integer'))], content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope')),
        ]
    )]
    public function store(LoginRequest $request, BuildCurrentUserProfile $profile): JsonResponse
    {
        $request->authenticate();
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return ApiResponse::success(
            data: $profile->handle($request->user()),
            message: 'Logged in.'
        );
    }

    #[OA\Post(
        path: '/auth/logout',
        operationId: 'logout',
        security: [['sanctumSession' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope')),
        ]
    )]
    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return ApiResponse::success(message: 'Logged out.');
    }
}

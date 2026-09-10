<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\BuildCurrentUserProfile;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CurrentUserController
{
    #[OA\Get(
        path: '/me',
        operationId: 'getCurrentUser',
        security: [['sanctumSession' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorEnvelope')
            ),
        ]
    )]
    public function __invoke(Request $request, BuildCurrentUserProfile $profile): JsonResponse
    {
        return ApiResponse::success(
            data: $profile->handle($request->user()),
        );
    }
}

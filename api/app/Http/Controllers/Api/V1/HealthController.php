<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class HealthController
{
    #[OA\Get(
        path: '/health',
        operationId: 'getApiHealth',
        tags: ['Foundation'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccessEnvelope')
            ),
        ]
    )]
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'service' => 'ant-laravel-admin-api',
            'status' => 'ok',
        ]);
    }
}

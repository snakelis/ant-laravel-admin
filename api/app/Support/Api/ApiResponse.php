<?php

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Return a successful JSON API envelope.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
        array $meta = [],
        string $code = 'ok'
    ): JsonResponse {
        $payload = [
            'success' => true,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return a failed JSON API envelope.
     *
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $meta
     */
    public static function error(
        string $code,
        string $message,
        int $status,
        array $errors = [],
        array $meta = []
    ): JsonResponse {
        $payload = [
            'success' => false,
            'code' => $code,
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return a paginated JSON API envelope.
     *
     * @param  array<int, mixed>|null  $data
     */
    public static function paginated(
        LengthAwarePaginatorContract|PaginatorContract $paginator,
        ?array $data = null,
        string $message = 'OK'
    ): JsonResponse {
        $meta = [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'count' => $paginator->count(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ];

        if ($paginator instanceof LengthAwarePaginatorContract) {
            $meta['pagination'] += [
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ];
        }

        return self::success(
            data: $data ?? array_values($paginator->items()),
            message: $message,
            meta: $meta,
        );
    }
}

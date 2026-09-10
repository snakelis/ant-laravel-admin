<?php

namespace Tests\Unit\Support\Api;

use App\Support\Api\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_paginated_response_uses_standard_pagination_meta(): void
    {
        $response = ApiResponse::paginated(new LengthAwarePaginator(
            items: [['id' => 1], ['id' => 2]],
            total: 5,
            perPage: 2,
            currentPage: 1,
        ));

        $payload = $response->getData(assoc: true);

        $this->assertTrue($payload['success']);
        $this->assertSame('ok', $payload['code']);
        $this->assertSame([['id' => 1], ['id' => 2]], $payload['data']);
        $this->assertSame(1, $payload['meta']['pagination']['current_page']);
        $this->assertSame(2, $payload['meta']['pagination']['per_page']);
        $this->assertSame(5, $payload['meta']['pagination']['total']);
        $this->assertSame(3, $payload['meta']['pagination']['last_page']);
        $this->assertTrue($payload['meta']['pagination']['has_more_pages']);
    }
}

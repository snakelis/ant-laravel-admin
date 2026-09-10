<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_limit_survives_ip_and_case_changes_and_expires(): void
    {
        User::factory()->create(['email' => 'limited@example.com', 'password' => 'correct-password']);

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($i + 1)])
                ->postJson('/api/v1/auth/login', ['email' => 'limited@example.com', 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.100'])
            ->postJson('/api/v1/auth/login', ['email' => 'LIMITED@example.com', 'password' => 'correct-password'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_requests')
            ->assertJsonPath('success', false)
            ->assertHeader('Retry-After');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertGuest('web');

        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/login', ['email' => 'limited@example.com', 'password' => 'correct-password'])
            ->assertOk();
        $this->travelBack();
    }

    public function test_ip_limit_applies_across_accounts_and_other_ips_remain_available(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])
                ->postJson('/api/v1/auth/login', ['email' => "unknown{$i}@example.com", 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'another@example.com', 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])
            ->postJson('/api/v1/auth/login', ['email' => 'another@example.com', 'password' => 'wrong'])
            ->assertUnprocessable();
    }
}

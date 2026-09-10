<?php

namespace Tests\Feature\Api\V1\System;

use App\Models\Permission;
use App\Models\User;
use App\Services\Auth\PasswordPolicy;
use Database\Seeders\Foundation\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_length_configuration_is_not_silently_weakened(): void
    {
        config(['foundation.password.min_length' => 'invalid']);
        $this->expectException(\InvalidArgumentException::class);
        app(PasswordPolicy::class)->settings();
    }

    public function test_default_policy_requires_length_and_all_character_groups(): void
    {
        $policy = app(PasswordPolicy::class);
        foreach (['Short1!', 'longlowercase1!', 'LongPassword!!', 'LongPassword12'] as $value) {
            $this->assertTrue(Validator::make(['password' => $value], ['password' => [$policy->rule()]])->fails());
        }
        $this->assertFalse(Validator::make(['password' => 'LongPassword12!'], ['password' => [$policy->rule()]])->fails());
        $this->assertSame(12, $policy->settings()['min_length']);
    }

    public function test_configured_policy_is_used_by_command_api_and_options(): void
    {
        config(['foundation.password' => ['min_length' => 5, 'mixed_case' => false, 'numbers' => false, 'symbols' => false]]);
        $this->seed(RbacSeeder::class);
        $this->artisan('foundation:create-admin')
            ->expectsQuestion('管理员姓名', 'Policy Tester')
            ->expectsQuestion('管理员邮箱', 'policy@example.test')
            ->expectsQuestion('密码（至少 5 位，不限制字符组合）', 'plain')
            ->expectsQuestion('再次输入密码', 'plain')
            ->assertSuccessful();
        $actor = User::where('email', 'policy@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('plain', $actor->password));
        Sanctum::actingAs($actor);
        $this->getJson('/api/v1/management-options/user-password-policy')->assertOk()
            ->assertJsonPath('data.min_length', 5)->assertJsonPath('data.mixed_case', false);
        $this->postJson('/api/v1/users', ['name' => 'Sample', 'email' => 'sample@example.test', 'password' => 'tiny'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $id = $this->postJson('/api/v1/users', ['name' => 'Sample', 'email' => 'sample@example.test', 'password' => 'plain'])
            ->assertCreated()->json('data.id');
        $this->patchJson('/api/v1/users/'.$id, ['password' => 'tiny'])->assertUnprocessable();
        $this->patchJson('/api/v1/users/'.$id, ['password' => 'other'])->assertOk();
        $hash = User::findOrFail($id)->password;
        $this->assertTrue(Hash::check('other', $hash));
        $this->patchJson('/api/v1/users/'.$id, ['name' => 'Renamed'])->assertOk();
        $this->assertSame($hash, User::findOrFail($id)->password);
    }

    public function test_policy_options_keep_authentication_and_permission_boundary(): void
    {
        $url = '/api/v1/management-options/user-password-policy';
        $this->getJson($url)->assertUnauthorized();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson($url)->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('system.user.update', 'web'));
        $this->getJson($url)->assertOk()->assertJsonPath('data.min_length', 12);
    }
}

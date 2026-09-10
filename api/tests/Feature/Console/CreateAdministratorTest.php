<?php

namespace Tests\Feature\Console;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\Foundation\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_never_creates_or_promotes_an_account(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('db:seed', ['--class' => RbacSeeder::class, '--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $password = $user->password;

        $this->artisan('db:seed', ['--class' => RbacSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($password, $user->refresh()->password);
        $this->assertFalse($user->hasRole('super-admin'));
    }

    public function test_command_creates_an_administrator_with_a_hashed_password(): void
    {
        $this->seed(RbacSeeder::class);
        $this->artisan('foundation:create-admin')
            ->expectsQuestion('管理员姓名', 'Administrator')
            ->expectsQuestion('管理员邮箱', 'new-admin@example.com')
            ->expectsQuestion('密码（至少 12 位，包含大小写字母、数字和符号）', 'Test-only-Secure42!')
            ->expectsQuestion('再次输入密码', 'Test-only-Secure42!')
            ->expectsOutput('管理员创建成功。')
            ->assertSuccessful();

        $user = User::query()->where('email', 'new-admin@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('super-admin'));
        $this->assertTrue(Hash::check('Test-only-Secure42!', $user->password));
    }

    public function test_command_rejects_an_existing_email_without_modifying_the_account(): void
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create(['email' => 'existing@example.com']);
        $original = $user->getAttributes();
        $this->artisan('foundation:create-admin')
            ->expectsQuestion('管理员姓名', 'Replacement')
            ->expectsQuestion('管理员邮箱', 'existing@example.com')
            ->expectsQuestion('密码（至少 12 位，包含大小写字母、数字和符号）', 'Test-only-Secure42!')
            ->expectsQuestion('再次输入密码', 'Test-only-Secure42!')
            ->assertFailed();

        $this->assertTrue($original == $user->refresh()->getAttributes());
        $this->assertFalse($user->hasRole('super-admin'));
    }

    public function test_command_rejects_weak_or_unconfirmed_passwords(): void
    {
        $this->seed(RbacSeeder::class);
        foreach ([['weak', 'weak'], ['Test-only-Secure42!', 'different']] as [$password, $confirmation]) {
            $this->artisan('foundation:create-admin')
                ->expectsQuestion('管理员姓名', 'Administrator')
                ->expectsQuestion('管理员邮箱', 'new-admin@example.com')
                ->expectsQuestion('密码（至少 12 位，包含大小写字母、数字和符号）', $password)
                ->expectsQuestion('再次输入密码', $confirmation)
                ->assertFailed();
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_command_requires_seeded_role_and_interactive_input(): void
    {
        $this->artisan('foundation:create-admin')->assertFailed();
        Role::query()->create(['name' => 'super-admin', 'guard_name' => 'web']);
        $this->artisan('foundation:create-admin', ['--no-interaction' => true])->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
